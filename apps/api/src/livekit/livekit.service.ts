import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { AccessToken } from 'livekit-server-sdk';

@Injectable()
export class LivekitService {
  constructor(private readonly config: ConfigService) {}

  get publicUrl() {
    return this.config.get<string>('LIVEKIT_PUBLIC_URL') || 'ws://localhost:7880';
  }

  async createParticipantToken(params: {
    roomName: string;
    identity: string;
    name: string;
    metadata?: string;
  }) {
    const apiKey = this.config.get<string>('LIVEKIT_API_KEY') || 'devkey';
    const apiSecret = this.config.get<string>('LIVEKIT_API_SECRET') || 'secret';

    const at = new AccessToken(apiKey, apiSecret, {
      identity: params.identity,
      name: params.name,
      metadata: params.metadata,
      ttl: '6h',
    });
    at.addGrant({
      roomJoin: true,
      room: params.roomName,
      canPublish: true,
      canSubscribe: true,
      canPublishData: true,
    });
    return at.toJwt();
  }
}
