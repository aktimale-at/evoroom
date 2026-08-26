import { Body, Controller, Delete, Get, Param, Post, Req, UseGuards } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { RoomsService } from './rooms.service';
import { CreateRoomDto } from './dto/create-room.dto';
import { JoinRoomDto } from './dto/join-room.dto';

@Controller('rooms')
export class RoomsController {
  constructor(private readonly rooms: RoomsService) {}

  @UseGuards(AuthGuard('jwt'))
  @Get()
  list(@Req() req: { user: { id: string } }) {
    return this.rooms.listForHost(req.user.id);
  }

  @UseGuards(AuthGuard('jwt'))
  @Post()
  create(@Req() req: { user: { id: string } }, @Body() dto: CreateRoomDto) {
    return this.rooms.create(req.user.id, dto);
  }

  @Get(':slug')
  getPublic(@Param('slug') slug: string) {
    return this.rooms.getBySlug(slug);
  }

  @UseGuards(AuthGuard('jwt'))
  @Delete(':slug')
  remove(@Req() req: { user: { id: string } }, @Param('slug') slug: string) {
    return this.rooms.remove(req.user.id, slug);
  }

  @Post(':slug/join')
  joinGuest(@Param('slug') slug: string, @Body() dto: JoinRoomDto) {
    return this.rooms.joinAsGuest(slug, dto);
  }

  @UseGuards(AuthGuard('jwt'))
  @Post(':slug/host')
  joinHost(@Req() req: { user: { id: string } }, @Param('slug') slug: string) {
    return this.rooms.joinAsHost(req.user.id, slug);
  }
}
