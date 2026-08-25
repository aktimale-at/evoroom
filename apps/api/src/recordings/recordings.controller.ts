import {
  Controller,
  Get,
  Param,
  Post,
  Req,
  Res,
  StreamableFile,
  UseGuards,
} from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import type { Response } from 'express';
import { RecordingsService } from './recordings.service';

@Controller()
@UseGuards(AuthGuard('jwt'))
export class RecordingsController {
  constructor(private readonly recordings: RecordingsService) {}

  @Post('rooms/:slug/meetings/:meetingId/recording/start')
  start(
    @Req() req: { user: { id: string } },
    @Param('slug') slug: string,
    @Param('meetingId') meetingId: string,
  ) {
    return this.recordings.start(req.user.id, slug, meetingId);
  }

  @Post('rooms/:slug/meetings/:meetingId/recording/stop')
  stop(
    @Req() req: { user: { id: string } },
    @Param('slug') slug: string,
    @Param('meetingId') meetingId: string,
  ) {
    return this.recordings.stop(req.user.id, slug, meetingId);
  }

  @Get('rooms/:slug/meetings/:meetingId/recording')
  status(
    @Req() req: { user: { id: string } },
    @Param('slug') slug: string,
    @Param('meetingId') meetingId: string,
  ) {
    return this.recordings.status(req.user.id, slug, meetingId);
  }

  @Get('recordings')
  list(@Req() req: { user: { id: string } }) {
    return this.recordings.listForHost(req.user.id);
  }

  @Get('recordings/:id/download')
  async download(
    @Req() req: { user: { id: string } },
    @Param('id') id: string,
    @Res({ passthrough: true }) res: Response,
  ) {
    const { stream, filename, size, contentType } = await this.recordings.getDownloadStream(
      req.user.id,
      id,
    );
    res.setHeader('Content-Type', contentType);
    res.setHeader(
      'Content-Disposition',
      `attachment; filename*=UTF-8''${encodeURIComponent(filename)}`,
    );
    if (size != null) res.setHeader('Content-Length', String(size));
    return new StreamableFile(stream);
  }
}
