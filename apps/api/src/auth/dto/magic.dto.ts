import { IsEmail, IsString, Length } from 'class-validator';

export class RequestCodeDto {
  @IsEmail()
  email!: string;
}

export class VerifyCodeDto {
  @IsEmail()
  email!: string;

  @IsString()
  @Length(6, 6)
  code!: string;
}
