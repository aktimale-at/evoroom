import { Module } from '@nestjs/common';
import { LivekitModule } from '../livekit/livekit.module';
import { MinioModule } from '../minio/minio.module';
import { RecordingsController } from './recordings.controller';
import { RecordingsService } from './recordings.service';

@Module({
  imports: [LivekitModule, MinioModule],
  controllers: [RecordingsController],
  providers: [RecordingsService],
})
export class RecordingsModule {}
