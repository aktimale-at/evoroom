import { Injectable, Logger, NotFoundException } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import * as Minio from 'minio';
import type { Readable } from 'stream';

@Injectable()
export class MinioService {
  private readonly logger = new Logger(MinioService.name);
  private readonly client: Minio.Client;
  private readonly bucket: string;

  constructor(private readonly config: ConfigService) {
    const endPoint = this.config.get<string>('MINIO_ENDPOINT') || 'localhost';
    const port = Number(this.config.get<string>('MINIO_PORT') || 9000);
    const useSSL = (this.config.get<string>('MINIO_USE_SSL') || 'false') === 'true';
    const accessKey = this.config.get<string>('MINIO_ACCESS_KEY') || 'evoroom';
    const secretKey = this.config.get<string>('MINIO_SECRET_KEY') || 'evoroomsecret';
    this.bucket = this.config.get<string>('MINIO_BUCKET') || 'evoroom';

    this.client = new Minio.Client({
      endPoint,
      port,
      useSSL,
      accessKey,
      secretKey,
    });
  }

  async getObjectStream(objectKey: string): Promise<{
    stream: Readable;
    size?: number;
    contentType: string;
  }> {
    const key = objectKey.replace(/^\//, '');
    try {
      const stat = await this.client.statObject(this.bucket, key);
      const stream = await this.client.getObject(this.bucket, key);
      return {
        stream,
        size: stat.size,
        contentType: (stat.metaData?.['content-type'] as string) || 'video/mp4',
      };
    } catch (err) {
      this.logger.warn(`MinIO get failed for ${key}: ${err instanceof Error ? err.message : err}`);
      throw new NotFoundException('Файл записи не найден в хранилище');
    }
  }

  async statObject(objectKey: string): Promise<{ size: number } | null> {
    const key = objectKey.replace(/^\//, '');
    try {
      const stat = await this.client.statObject(this.bucket, key);
      return { size: stat.size };
    } catch (err) {
      this.logger.warn(`MinIO stat failed for ${key}: ${err instanceof Error ? err.message : err}`);
      return null;
    }
  }

  async removeObject(objectKey: string): Promise<void> {
    const key = objectKey.replace(/^\//, '');
    try {
      await this.client.removeObject(this.bucket, key);
    } catch (err) {
      this.logger.warn(
        `MinIO remove failed for ${key}: ${err instanceof Error ? err.message : err}`,
      );
    }
  }
}
