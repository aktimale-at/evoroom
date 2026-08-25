import { Controller, Get } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';

@Controller('health')
export class HealthController {
  constructor(private readonly prisma: PrismaService) {}

  @Get()
  async check() {
    let db = false;
    try {
      await this.prisma.$queryRaw`SELECT 1`;
      db = true;
    } catch {
      db = false;
    }
    return {
      ok: true,
      service: 'evoroom-api',
      db,
      livekit: Boolean(process.env.LIVEKIT_URL),
      time: new Date().toISOString(),
    };
  }
}
