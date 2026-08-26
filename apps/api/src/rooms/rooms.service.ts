import {
  Injectable,
  NotFoundException,
  ForbiddenException,
  BadRequestException,
  Logger,
} from '@nestjs/common';
import * as bcrypt from 'bcryptjs';
import { PrismaService } from '../prisma/prisma.service';
import { LivekitService } from '../livekit/livekit.service';
import { MinioService } from '../minio/minio.service';
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
  private readonly logger = new Logger(RoomsService.name);

  constructor(
    private readonly prisma: PrismaService,
    private readonly livekit: LivekitService,
    private readonly minio: MinioService,
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
    return generateRoomSlug(12);
  }

  private assertActiveRoom<T extends { deletedAt: Date | null }>(
    room: T | null,
  ): asserts room is T {
    if (!room || room.deletedAt) throw new NotFoundException('Комната не найдена');
  }

  listForHost(hostId: string) {
    return this.prisma.room.findMany({
      where: { hostId, deletedAt: null },
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

  /**
   * По умолчанию — soft-delete: комната скрывается, видео остаются.
   * deleteVideos=true — удаляет файлы в MinIO и комнату полностью.
   */
  async remove(hostId: string, slug: string, deleteVideos = false) {
    const room = await this.prisma.room.findUnique({ where: { slug } });
    if (!room || room.deletedAt) throw new NotFoundException('Комната не найдена');
    if (room.hostId !== hostId) throw new ForbiddenException('Нет доступа');

    if (deleteVideos) {
      const recordings = await this.prisma.recording.findMany({
        where: { meeting: { roomId: room.id }, objectKey: { not: null } },
        select: { id: true, objectKey: true },
      });
      for (const rec of recordings) {
        if (rec.objectKey) await this.minio.removeObject(rec.objectKey);
      }
      await this.prisma.room.delete({ where: { id: room.id } });
      this.logger.log(`Room ${slug} hard-deleted with ${recordings.length} video(s)`);
      return { ok: true, slug, deletedVideos: recordings.length, soft: false };
    }

    await this.prisma.room.update({
      where: { id: room.id },
      data: { deletedAt: new Date() },
    });
    this.logger.log(`Room ${slug} soft-deleted (videos kept)`);
    return { ok: true, slug, deletedVideos: 0, soft: true };
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
        deletedAt: true,
        host: { select: { id: true, name: true } },
      },
    });
    this.assertActiveRoom(room);
    const { passwordHash, deletedAt: _d, ...publicRoom } = room;
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
    this.assertActiveRoom(room);

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
    this.assertActiveRoom(room);
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
