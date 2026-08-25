const API_BASE = import.meta.env.VITE_API_URL || '';

export type AuthUser = {
  id: string;
  email: string;
  name: string;
};

type AuthResponse = {
  accessToken: string;
  user: AuthUser;
};

function authHeaders(): HeadersInit {
  const token = localStorage.getItem('evoroom_token');
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...authHeaders(),
      ...(init?.headers || {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const message = (data as { message?: string | string[] }).message;
    throw new Error(Array.isArray(message) ? message.join(', ') : message || res.statusText);
  }
  return data as T;
}

export const api = {
  register: (body: { email: string; name: string; password: string }) =>
    request<AuthResponse>('/api/auth/register', { method: 'POST', body: JSON.stringify(body) }),
  login: (body: { email: string; password: string }) =>
    request<AuthResponse>('/api/auth/login', { method: 'POST', body: JSON.stringify(body) }),
  requestCode: (email: string) =>
    request<{ ok: boolean; message: string; devCode?: string }>('/api/auth/magic/request', {
      method: 'POST',
      body: JSON.stringify({ email }),
    }),
  verifyCode: (body: { email: string; code: string }) =>
    request<AuthResponse>('/api/auth/magic/verify', { method: 'POST', body: JSON.stringify(body) }),
  me: () => request<AuthUser & { role: string }>('/api/auth/me'),
  rooms: () =>
    request<
      Array<{
        id: string;
        slug: string;
        title: string;
        collabMode: string;
        videoQuality: string;
        maxParticipants: number;
        createdAt: string;
      }>
    >('/api/rooms'),
  createRoom: (body: {
    title: string;
    password?: string;
    collabMode?: string;
    videoQuality?: string;
  }) =>
    request<{ id: string; slug: string; title: string }>('/api/rooms', {
      method: 'POST',
      body: JSON.stringify(body),
    }),
  getRoom: (slug: string) =>
    request<{
      id: string;
      slug: string;
      title: string;
      hasPassword: boolean;
      host: { id: string; name: string };
      collabMode: string;
      videoQuality: string;
    }>(`/api/rooms/${slug}`),
  joinGuest: (slug: string, body: { name: string; password?: string }) =>
    request<{
      room: { title: string; slug: string };
      meetingId: string;
      livekit: { url: string; token: string };
      role: string;
      displayName: string;
    }>(`/api/rooms/${slug}/join`, { method: 'POST', body: JSON.stringify(body) }),
  joinHost: (slug: string) =>
    request<{
      room: { title: string; slug: string };
      meetingId: string;
      livekit: { url: string; token: string };
      role: string;
      displayName: string;
    }>(`/api/rooms/${slug}/host`, { method: 'POST' }),
  startRecording: (slug: string, meetingId: string) =>
    request<{
      id: string;
      status: string;
      egressId: string | null;
      objectKey: string | null;
      startedAt: string | null;
    }>(`/api/rooms/${slug}/meetings/${meetingId}/recording/start`, { method: 'POST' }),
  stopRecording: (slug: string, meetingId: string) =>
    request<{
      id: string;
      status: string;
      egressId: string | null;
      objectKey: string | null;
      durationSec: number | null;
      startedAt: string | null;
      endedAt: string | null;
    }>(`/api/rooms/${slug}/meetings/${meetingId}/recording/stop`, { method: 'POST' }),
  recordingStatus: (slug: string, meetingId: string) =>
    request<{
      status: string;
      recording: {
        id: string;
        status: string;
        objectKey: string | null;
        durationSec: number | null;
      } | null;
    }>(`/api/rooms/${slug}/meetings/${meetingId}/recording`),
  health: () => request<{ ok: boolean; db: boolean }>('/api/health'),
};
