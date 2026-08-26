import {
  Injectable,
  NotFoundException,
  ForbiddenException,
  BadRequestException,
} from '@nestjs/common';
import * as bcrypt from 'bcryptjs';
import { PrismaService } from '../prisma/prisma.service';
import { LivekitService } from '../livekit/livekit.service';
import { CreateRoomDto } from './dto/create-room.dto';
import { JoinRoomDto } from './dto/join-room.dto';

function generateRoomSlug(length = 8) {
  const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
  let out = '';
  for (let i = 0; i < length; i++) {
    out += alphabet[Math.floor(Math.random() * alphabet.length)];
  }
  return out;
}

@Injectable()
export class RoomsService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly livekit: LivekitService,
  ) {}

  private async uniqueSlug() {
    for (let attempt = 0; attempt < 8; attempt++) {
      const slug = generateRoomSlug(8);
      const exists = await this.prisma.room.findUnique({
        where: { slug },
        select: { id: true },
      });
      if (!exists) return slug;
    }
    // запасной вариант длиннее
    return generateRoomSlug(12);
  }

  listForHost(hostId: string) {
    return this.prisma.room.findMany({
      where: { hostId },
      orderBy: { updatedAt: 'desc' },
      select: {
        id: true,
        slug: true,
        title: true,
        collabMode: true,
        maxParticipants: true,
        videoQuality: true,
        createdAt: true,
        updatedAt: true,
        _count: { select: { meetings: true } },
      },
    });
  }

  async create(hostId: string, dto: CreateRoomDto) {
    const slug = await this.uniqueSlug();
    const passwordHash = dto.password ? await bcrypt.hash(dto.password, 10) : null;
    return this.prisma.room.create({
      data: {
        title: dto.title.trim(),
        slug,
        hostId,
        passwordHash,
        collabMode: dto.collabMode || 'host_control',
        maxParticipants: dto.maxParticipants || 10,
        videoQuality: dto.videoQuality || '720p',
      },
      select: {
        id: true,
        slug: true,
        title: true,
        collabMode: true,
        maxParticipants: true,
        videoQuality: true,
        createdAt: true,
      },
    });
  }

  async remove(hostId: string, slug: string) {
    const room = await this.prisma.room.findUnique({ where: { slug } });
    if (!room) throw new NotFoundException('Комната не найдена');
    if (room.hostId !== hostId) throw new ForbiddenException('Нет доступа');

    await this.prisma.room.delete({ where: { id: room.id } });
    return { ok: true, slug };
  }

  async getBySlug(slug: string) {
    const room = await this.prisma.room.findUnique({
      where: { slug },
      select: {
        id: true,
        slug: true,
        title: true,
        collabMode: true,
        maxParticipants: true,
        videoQuality: true,
        passwordHash: true,
        host: { select: { id: true, name: true } },
      },
    });
    if (!room) throw new NotFoundException('Комната не найдена');
    const { passwordHash, ...publicRoom } = room;
    return {
      ...publicRoom,
      hasPassword: Boolean(passwordHash),
    };
  }

  async joinAsGuest(slug: string, dto: JoinRoomDto) {
    const room = await this.prisma.room.findUnique({
      where: { slug },
      include: { host: { select: { id: true, name: true } } },
    });
    if (!room) throw new NotFoundException('Комната не найдена');

    if (room.passwordHash) {
      if (!dto.password) throw new ForbiddenException('Нужен пароль комнаты');
      const ok = await bcrypt.compare(dto.password, room.passwordHash);
      if (!ok) throw new ForbiddenException('Неверный пароль');
    }

    const name = dto.name.trim();
    if (!name) throw new BadRequestException('Укажите имя');

    const meeting = await this.prisma.meeting.create({
      data: {
        roomId: room.id,
        hostId: room.hostId,
        livekitRoom: `room-${room.slug}`,
      },
    });

    const token = await this.livekit.createParticipantToken({
      roomName: `room-${room.slug}`,
      identity: `guest-${Date.now()}`,
      name,
      metadata: JSON.stringify({ role: 'participant', meetingId: meeting.id }),
    });

    return {
      room: {
        id: room.id,
        slug: room.slug,
        title: room.title,
        collabMode: room.collabMode,
        videoQuality: room.videoQuality,
        hostName: room.host.name,
      },
      meetingId: meeting.id,
      livekit: {
        url: this.livekit.publicUrl,
        token,
      },
      role: 'participant' as const,
      displayName: name,
    };
  }

  async joinAsHost(hostId: string, slug: string) {
    const room = await this.prisma.room.findUnique({ where: { slug } });
    if (!room) throw new NotFoundException('Комната не найдена');
    if (room.hostId !== hostId) throw new ForbiddenException('Нет доступа');

    const meeting = await this.prisma.meeting.create({
      data: {
        roomId: room.id,
        hostId,
        livekitRoom: `room-${room.slug}`,
      },
    });

    const host = await this.prisma.user.findUnique({ where: { id: hostId } });
    const token = await this.livekit.createParticipantToken({
      roomName: `room-${room.slug}`,
      identity: `host-${hostId}`,
      name: host?.name || 'Ведущий',
      metadata: JSON.stringify({ role: 'host', meetingId: meeting.id }),
    });

    return {
      room: {
        id: room.id,
        slug: room.slug,
        title: room.title,
        collabMode: room.collabMode,
        videoQuality: room.videoQuality,
      },
      meetingId: meeting.id,
      livekit: {
        url: this.livekit.publicUrl,
        token,
      },
      role: 'host' as const,
      displayName: host?.name || 'Ведущий',
    };
  }
}
