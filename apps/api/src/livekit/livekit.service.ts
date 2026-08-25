import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import {
  AccessToken,
  EgressClient,
  EncodedFileOutput,
  EncodedFileType,
  EncodingOptionsPreset,
  S3Upload,
  type EgressInfo,
} from 'livekit-server-sdk';

@Injectable()
export class LivekitService {
  private readonly logger = new Logger(LivekitService.name);

  constructor(private readonly config: ConfigService) {}

  get publicUrl() {
    return this.config.get<string>('LIVEKIT_PUBLIC_URL') || 'ws://localhost:7880';
  }

  /** HTTP host for Room/Egress API (ws → http). */
  private get apiHost() {
    const raw =
      this.config.get<string>('LIVEKIT_API_URL') ||
      this.config.get<string>('LIVEKIT_URL') ||
      'http://localhost:7880';
    return raw.replace(/^ws/i, 'http');
  }

  private get apiKey() {
    return this.config.get<string>('LIVEKIT_API_KEY') || 'devkey';
  }

  private get apiSecret() {
    return this.config.get<string>('LIVEKIT_API_SECRET') || 'secret';
  }

  private egressClient() {
    return new EgressClient(this.apiHost, this.apiKey, this.apiSecret);
  }

  async createParticipantToken(params: {
    roomName: string;
    identity: string;
    name: string;
    metadata?: string;
  }) {
    const at = new AccessToken(this.apiKey, this.apiSecret, {
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

  async startRoomCompositeRecording(params: {
    roomName: string;
    filepath: string;
  }): Promise<EgressInfo> {
    const accessKey = this.config.get<string>('MINIO_ACCESS_KEY') || 'evoroom';
    const secret = this.config.get<string>('MINIO_SECRET_KEY') || 'evoroomsecret';
    const bucket = this.config.get<string>('MINIO_BUCKET') || 'evoroom';
    // Endpoint must be reachable from the egress container (docker DNS).
    const endpoint =
      this.config.get<string>('MINIO_EGRESS_ENDPOINT') || 'http://minio:9000';

    const fileOutput = new EncodedFileOutput({
      fileType: EncodedFileType.MP4,
      filepath: params.filepath,
      output: {
        case: 's3',
        value: new S3Upload({
          accessKey,
          secret,
          bucket,
          endpoint,
          region: 'us-east-1',
          forcePathStyle: true,
        }),
      },
    });

    this.logger.log(`Start RoomComposite egress room=${params.roomName} file=${params.filepath}`);
    return this.egressClient().startRoomCompositeEgress(
      params.roomName,
      { file: fileOutput },
      {
        layout: 'grid',
        encodingOptions: EncodingOptionsPreset.H264_720P_30,
      },
    );
  }

  async stopRecording(egressId: string): Promise<EgressInfo> {
    this.logger.log(`Stop egress ${egressId}`);
    return this.egressClient().stopEgress(egressId);
  }

  async getEgress(egressId: string): Promise<EgressInfo | undefined> {
    const list = await this.egressClient().listEgress({ egressId });
    return list[0];
  }
}
