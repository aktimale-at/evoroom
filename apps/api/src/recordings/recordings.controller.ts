import { Controller, Get, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { RecordingsService } from './recordings.service';

@Controller('rooms/:slug/meetings/:meetingId/recording')
@UseGuards(AuthGuard('jwt'))
export class RecordingsController {
  constructor(private readonly recordings: RecordingsService) {}

  @Post('start')
  start(
    @Req() req: { user: { id: string } },
    @Param('slug') slug: string,
    @Param('meetingId') meetingId: string,
  ) {
    return this.recordings.start(req.user.id, slug, meetingId);
  }

  @Post('stop')
  stop(
    @Req() req: { user: { id: string } },
    @Param('slug') slug: string,
    @Param('meetingId') meetingId: string,
  ) {
    return this.recordings.stop(req.user.id, slug, meetingId);
  }

  @Get()
  status(
    @Req() req: { user: { id: string } },
    @Param('slug') slug: string,
    @Param('meetingId') meetingId: string,
  ) {
    return this.recordings.status(req.user.id, slug, meetingId);
  }
}
