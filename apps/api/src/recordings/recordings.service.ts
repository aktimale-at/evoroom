import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  Logger,
  NotFoundException,
} from '@nestjs/common';
import { RecordingStatus } from '@prisma/client';
import type { Readable } from 'stream';
import { PrismaService } from '../prisma/prisma.service';
import { LivekitService } from '../livekit/livekit.service';
import { MinioService } from '../minio/minio.service';

@Injectable()
export class RecordingsService {
  private readonly logger = new Logger(RecordingsService.name);

  constructor(
    private readonly prisma: PrismaService,
    private readonly livekit: LivekitService,
    private readonly minio: MinioService,
  ) {}

  async start(hostId: string, slug: string, meetingId: string) {
    const meeting = await this.loadHostMeeting(hostId, slug, meetingId);
    const livekitRoom = meeting.livekitRoom || `room-${meeting.room.slug}`;

    const active = await this.prisma.recording.findFirst({
      where: {
        meetingId: meeting.id,
        status: { in: [RecordingStatus.RECORDING, RecordingStatus.PROCESSING] },
      },
    });
    if (active) {
      throw new BadRequestException('Запись уже идёт');
    }

    const recording = await this.prisma.recording.create({
      data: {
        meetingId: meeting.id,
        status: RecordingStatus.IDLE,
        startedAt: new Date(),
      },
    });

    const objectKey = `recordings/${meeting.roomId}/${meeting.id}/${recording.id}.mp4`;

    try {
      const info = await this.livekit.startRoomCompositeRecording({
        roomName: livekitRoom,
        filepath: objectKey,
      });

      const updated = await this.prisma.recording.update({
        where: { id: recording.id },
        data: {
          status: RecordingStatus.RECORDING,
          egressId: info.egressId,
          objectKey,
        },
      });

      return {
        id: updated.id,
        status: updated.status,
        egressId: updated.egressId,
        objectKey: updated.objectKey,
        startedAt: updated.startedAt,
      };
    } catch (err) {
      this.logger.error('Failed to start egress', err instanceof Error ? err.stack : err);
      await this.prisma.recording.update({
        where: { id: recording.id },
        data: { status: RecordingStatus.FAILED, endedAt: new Date(), objectKey },
      });
      const message = err instanceof Error ? err.message : 'Не удалось начать запись';
      throw new BadRequestException(
        `Egress: ${message}. Убедитесь, что контейнер livekit-egress запущен.`,
      );
    }
  }

  async stop(hostId: string, slug: string, meetingId: string) {
    const meeting = await this.loadHostMeeting(hostId, slug, meetingId);

    const recording = await this.prisma.recording.findFirst({
      where: {
        meetingId: meeting.id,
        status: RecordingStatus.RECORDING,
      },
      orderBy: { startedAt: 'desc' },
    });
    if (!recording?.egressId) {
      throw new BadRequestException('Нет активной записи');
    }

    try {
      const info = await this.livekit.stopRecording(recording.egressId);
      const endedAt = new Date();
      const durationSec = recording.startedAt
        ? Math.max(0, Math.round((endedAt.getTime() - recording.startedAt.getTime()) / 1000))
        : null;

      const fileResult = info.fileResults?.[0];
      const objectKey = fileResult?.filename || recording.objectKey;

      const updated = await this.prisma.recording.update({
        where: { id: recording.id },
        data: {
          status: RecordingStatus.READY,
          endedAt,
          durationSec: durationSec ?? undefined,
          objectKey,
        },
      });

      return {
        id: updated.id,
        status: updated.status,
        egressId: updated.egressId,
        objectKey: updated.objectKey,
        durationSec: updated.durationSec,
        startedAt: updated.startedAt,
        endedAt: updated.endedAt,
      };
    } catch (err) {
      this.logger.error('Failed to stop egress', err instanceof Error ? err.stack : err);
      await this.prisma.recording.update({
        where: { id: recording.id },
        data: { status: RecordingStatus.FAILED, endedAt: new Date() },
      });
      const message = err instanceof Error ? err.message : 'Не удалось остановить запись';
      throw new BadRequestException(`Egress stop: ${message}`);
    }
  }

  async status(hostId: string, slug: string, meetingId: string) {
    const meeting = await this.loadHostMeeting(hostId, slug, meetingId);
    const recording = await this.prisma.recording.findFirst({
      where: { meetingId: meeting.id },
      orderBy: { createdAt: 'desc' },
    });
    if (!recording) {
      return { status: 'IDLE' as const, recording: null };
    }
    return {
      status: recording.status,
      recording: {
        id: recording.id,
        status: recording.status,
        egressId: recording.egressId,
        objectKey: recording.objectKey,
        durationSec: recording.durationSec,
        startedAt: recording.startedAt,
        endedAt: recording.endedAt,
      },
    };
  }

  async listForHost(hostId: string) {
    const rows = await this.prisma.recording.findMany({
      where: {
        meeting: { hostId },
        status: { in: [RecordingStatus.READY, RecordingStatus.ARCHIVED] },
        objectKey: { not: null },
      },
      orderBy: { createdAt: 'desc' },
      take: 50,
      include: {
        meeting: {
          select: {
            id: true,
            startedAt: true,
            room: { select: { id: true, title: true, slug: true, deletedAt: true } },
          },
        },
      },
    });

    return rows.map((r) => ({
      id: r.id,
      status: r.status,
      objectKey: r.objectKey,
      durationSec: r.durationSec,
      startedAt: r.startedAt,
      endedAt: r.endedAt,
      createdAt: r.createdAt,
      roomTitle: r.meeting.room.deletedAt
        ? `${r.meeting.room.title} (удалена)`
        : r.meeting.room.title,
      roomSlug: r.meeting.room.slug,
      roomDeleted: Boolean(r.meeting.room.deletedAt),
      meetingId: r.meeting.id,
    }));
  }

  async getDownloadStream(
    hostId: string,
    recordingId: string,
  ): Promise<{ stream: Readable; filename: string; size?: number; contentType: string }> {
    const recording = await this.prisma.recording.findUnique({
      where: { id: recordingId },
      include: { meeting: { include: { room: true } } },
    });
    if (!recording) throw new NotFoundException('Запись не найдена');
    if (recording.meeting.hostId !== hostId || recording.meeting.room.hostId !== hostId) {
      throw new ForbiddenException('Нет доступа к этой записи');
    }
    if (!recording.objectKey) {
      throw new BadRequestException('У записи нет файла');
    }
    if (
      recording.status !== RecordingStatus.READY &&
      recording.status !== RecordingStatus.ARCHIVED
    ) {
      throw new BadRequestException('Запись ещё не готова к скачиванию');
    }

    const file = await this.minio.getObjectStream(recording.objectKey);
    const base = recording.objectKey.split('/').pop() || `${recording.id}.mp4`;
    const safeTitle = recording.meeting.room.title
      .replace(/[^\p{L}\p{N}\-_]+/gu, '-')
      .replace(/-+/g, '-')
      .slice(0, 40);
    const filename = `${safeTitle || 'recording'}-${base}`;

    return {
      stream: file.stream,
      filename,
      size: file.size,
      contentType: file.contentType,
    };
  }

  async deleteOne(hostId: string, recordingId: string) {
    const recording = await this.loadHostRecording(hostId, recordingId);
    if (recording.objectKey) {
      await this.minio.removeObject(recording.objectKey);
    }
    await this.prisma.recording.delete({ where: { id: recording.id } });
    return { ok: true, id: recording.id };
  }

  async deleteMany(hostId: string, ids: string[]) {
    const unique = [...new Set(ids.filter(Boolean))];
    if (!unique.length) throw new BadRequestException('Не выбраны записи');

    let deleted = 0;
    for (const id of unique) {
      try {
        await this.deleteOne(hostId, id);
        deleted += 1;
      } catch (err) {
        this.logger.warn(
          `Skip delete ${id}: ${err instanceof Error ? err.message : err}`,
        );
      }
    }
    return { ok: true, deleted, requested: unique.length };
  }

  private async loadHostRecording(hostId: string, recordingId: string) {
    const recording = await this.prisma.recording.findUnique({
      where: { id: recordingId },
      include: { meeting: { include: { room: true } } },
    });
    if (!recording) throw new NotFoundException('Запись не найдена');
    if (recording.meeting.hostId !== hostId || recording.meeting.room.hostId !== hostId) {
      throw new ForbiddenException('Нет доступа к этой записи');
    }
    return recording;
  }

  private async loadHostMeeting(hostId: string, slug: string, meetingId: string) {
    const meeting = await this.prisma.meeting.findUnique({
      where: { id: meetingId },
      include: { room: true },
    });
    if (!meeting || meeting.room.slug !== slug) {
      throw new NotFoundException('Встреча не найдена');
    }
    if (meeting.hostId !== hostId || meeting.room.hostId !== hostId) {
      throw new ForbiddenException('Запись доступна только ведущему');
    }
    return meeting;
  }
}
