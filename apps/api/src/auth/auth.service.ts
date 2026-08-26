import {
  Injectable,
  UnauthorizedException,
  BadRequestException,
  Logger,
} from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import * as bcrypt from 'bcryptjs';
import { PrismaService } from '../prisma/prisma.service';
import { MailService } from '../mail/mail.service';
import { RegisterDto } from './dto/register.dto';
import { LoginDto } from './dto/login.dto';
import { RequestCodeDto, VerifyCodeDto } from './dto/magic.dto';

@Injectable()
export class AuthService {
  private readonly logger = new Logger(AuthService.name);

  constructor(
    private readonly prisma: PrismaService,
    private readonly jwt: JwtService,
    private readonly mail: MailService,
  ) {}

  private sign(user: { id: string; email: string; name: string }) {
    const accessToken = this.jwt.sign({ sub: user.id, email: user.email });
    return {
      accessToken,
      user: { id: user.id, email: user.email, name: user.name },
    };
  }

  async register(dto: RegisterDto) {
    const existing = await this.prisma.user.findUnique({ where: { email: dto.email.toLowerCase() } });
    if (existing) throw new BadRequestException('Email уже зарегистрирован');

    const passwordHash = await bcrypt.hash(dto.password, 10);
    const user = await this.prisma.user.create({
      data: {
        email: dto.email.toLowerCase(),
        passwordHash,
        name: dto.name.trim(),
      },
    });
    return this.sign(user);
  }

  async login(dto: LoginDto) {
    const user = await this.prisma.user.findUnique({ where: { email: dto.email.toLowerCase() } });
    if (!user || !user.passwordHash) throw new UnauthorizedException('Неверный email или пароль');
    const ok = await bcrypt.compare(dto.password, user.passwordHash);
    if (!ok) throw new UnauthorizedException('Неверный email или пароль');
    return this.sign(user);
  }

  async requestCode(dto: RequestCodeDto) {
    const email = dto.email.toLowerCase();
    const code = String(Math.floor(100000 + Math.random() * 900000));
    const expiresAt = new Date(Date.now() + 10 * 60 * 1000);

    let user = await this.prisma.user.findUnique({ where: { email } });
    if (!user) {
      user = await this.prisma.user.create({
        data: {
          email,
          name: email.split('@')[0],
        },
      });
    }

    await this.prisma.magicCode.create({
      data: { email, code, expiresAt, userId: user.id },
    });

    try {
      const result = await this.mail.sendMagicCode(email, code);
      if (!result.sent) {
        this.logger.warn(`SMTP не настроен — код для ${email}: ${code}`);
      }
      return {
        ok: true,
        message: result.sent
          ? 'Код отправлен на почту'
          : 'SMTP не настроен: код в логе API (и ниже в dev)',
        ...(result.sent || process.env.NODE_ENV === 'production'
          ? {}
          : { devCode: code }),
      };
    } catch (err) {
      this.logger.error(
        `Не удалось отправить код на ${email}`,
        err instanceof Error ? err.stack : err,
      );
      throw new BadRequestException(
        'Не удалось отправить письмо. Проверьте SMTP или попробуйте позже.',
      );
    }
  }

  async verifyCode(dto: VerifyCodeDto) {
    const email = dto.email.toLowerCase();
    const row = await this.prisma.magicCode.findFirst({
      where: {
        email,
        code: dto.code,
        usedAt: null,
        expiresAt: { gt: new Date() },
      },
      orderBy: { createdAt: 'desc' },
    });
    if (!row) throw new UnauthorizedException('Неверный или просроченный код');

    await this.prisma.magicCode.update({
      where: { id: row.id },
      data: { usedAt: new Date() },
    });

    const user = await this.prisma.user.findUnique({ where: { email } });
    if (!user) throw new UnauthorizedException('Пользователь не найден');
    return this.sign(user);
  }

  async me(userId: string) {
    const user = await this.prisma.user.findUnique({
      where: { id: userId },
      select: { id: true, email: true, name: true, role: true, createdAt: true },
    });
    if (!user) throw new UnauthorizedException();
    return user;
  }
}
