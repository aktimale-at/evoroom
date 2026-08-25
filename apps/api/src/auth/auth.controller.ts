import { Body, Controller, Get, Post, Req, UseGuards } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { AuthService } from './auth.service';
import { RegisterDto } from './dto/register.dto';
import { LoginDto } from './dto/login.dto';
import { RequestCodeDto, VerifyCodeDto } from './dto/magic.dto';

@Controller('auth')
export class AuthController {
  constructor(private readonly auth: AuthService) {}

  @Post('register')
  register(@Body() dto: RegisterDto) {
    return this.auth.register(dto);
  }

  @Post('login')
  login(@Body() dto: LoginDto) {
    return this.auth.login(dto);
  }

  @Post('magic/request')
  requestCode(@Body() dto: RequestCodeDto) {
    return this.auth.requestCode(dto);
  }

  @Post('magic/verify')
  verifyCode(@Body() dto: VerifyCodeDto) {
    return this.auth.verifyCode(dto);
  }

  @UseGuards(AuthGuard('jwt'))
  @Get('me')
  me(@Req() req: { user: { id: string } }) {
    return this.auth.me(req.user.id);
  }
}
