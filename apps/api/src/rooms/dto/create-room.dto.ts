import { IsIn, IsInt, IsOptional, IsString, Max, MaxLength, Min, MinLength } from 'class-validator';

export class CreateRoomDto {
  @IsString()
  @MinLength(2)
  @MaxLength(80)
  title!: string;

  @IsOptional()
  @IsString()
  @MinLength(4)
  @MaxLength(64)
  password?: string;

  @IsOptional()
  @IsIn(['host_control', 'collaborative'])
  collabMode?: 'host_control' | 'collaborative';

  @IsOptional()
  @IsInt()
  @Min(2)
  @Max(10)
  maxParticipants?: number;

  @IsOptional()
  @IsIn(['720p', '1080p'])
  videoQuality?: '720p' | '1080p';
}
