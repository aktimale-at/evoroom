import { Module } from '@nestjs/common';
import { RoomsService } from './rooms.service';
import { RoomsController } from './rooms.controller';
import { LivekitModule } from '../livekit/livekit.module';
import { MinioModule } from '../minio/minio.module';

@Module({
  imports: [LivekitModule, MinioModule],
  controllers: [RoomsController],
  providers: [RoomsService],
  exports: [RoomsService],
})
export class RoomsModule {}
