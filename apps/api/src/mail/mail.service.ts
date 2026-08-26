import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import nodemailer, { type Transporter } from 'nodemailer';

@Injectable()
export class MailService {
  private readonly logger = new Logger(MailService.name);
  private transporter: Transporter | null = null;

  constructor(private readonly config: ConfigService) {
    const host = this.config.get<string>('SMTP_HOST');
    if (!host) {
      this.logger.warn('SMTP_HOST не задан — письма только в лог');
      return;
    }

    const port = Number(this.config.get<string>('SMTP_PORT') || 587);
    const user = this.config.get<string>('SMTP_USER') || '';
    const pass = this.config.get<string>('SMTP_PASS') || '';

    this.transporter = nodemailer.createTransport({
      host,
      port,
      secure: port === 465,
      auth: user ? { user, pass } : undefined,
    });
  }

  get configured() {
    return Boolean(this.transporter);
  }

  async sendMagicCode(to: string, code: string) {
    const from =
      this.config.get<string>('MAIL_FROM') ||
      this.config.get<string>('SMTP_USER') ||
      'noreply@evo-room.com';
    const appUrl = this.config.get<string>('APP_URL') || 'https://evo-room.com';
    const subject = 'Код входа в Evoroom';
    const text = [
      `Ваш код для входа в Evoroom: ${code}`,
      '',
      'Код действует 10 минут.',
      `Если вы не запрашивали вход — просто проигнорируйте письмо.`,
      '',
      appUrl,
    ].join('\n');
    const html = `
      <div style="font-family:system-ui,sans-serif;max-width:420px;line-height:1.5">
        <p>Ваш код для входа в <strong>Evoroom</strong>:</p>
        <p style="font-size:28px;letter-spacing:0.2em;font-weight:700">${code}</p>
        <p style="color:#666">Код действует 10 минут.</p>
        <p style="color:#999;font-size:13px">Если вы не запрашивали вход — проигнорируйте это письмо.</p>
      </div>
    `;

    if (!this.transporter) {
      this.logger.log(`[mail:fallback] ${to} → ${code}`);
      return { sent: false, mode: 'log' as const };
    }

    await this.transporter.sendMail({ from, to, subject, text, html });
    this.logger.log(`Magic code sent to ${to}`);
    return { sent: true, mode: 'smtp' as const };
  }
}
