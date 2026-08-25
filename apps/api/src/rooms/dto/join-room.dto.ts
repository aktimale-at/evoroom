import { IsOptional, IsString, MaxLength, MinLength } from 'class-validator';

export class JoinRoomDto {
  @IsString()
  @MinLength(1)
  @MaxLength(40)
  name!: string;

  @IsOptional()
  @IsString()
  @MaxLength(64)
  password?: string;
}
