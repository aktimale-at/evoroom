# Evoroom

Психологическая студия: **видео (LiveKit) + холст + ЛК специалистов**, OSS, self-host в РФ.

Прототип UI: `index.php` (не production).  
ТЗ: `TZ-v2.md`.

## Стек

- **web** — React + TypeScript + Vite + LiveKit Client  
- **api** — NestJS + Prisma + PostgreSQL + JWT  
- **infra** — Docker: Postgres, Redis, MinIO, LiveKit, **Egress**  

Запись: Room Composite (grid) → MinIO бакет `evoroom/recordings/…`.

## Быстрый старт

### 1. Окружение

```bash
cp .env.example .env
```

### 2. Инфра

Нужен **Docker Desktop** (сейчас на машине может быть не установлен).

```bash
npm run infra:up
```

Поднимет Postgres, Redis, MinIO, LiveKit, Egress.

Без Docker API/web соберутся, но БД и видеокомната не заработают.

### 3. Зависимости и БД

```bash
npm install
cp .env apps/api/.env
npm run db:generate
npm run db:migrate
```

### 4. Запуск

```bash
npm run dev:api
npm run dev:web
```

- Web: http://localhost:5173  
- API: http://localhost:3001/api/health  

## Сценарий

1. Зарегистрировать специалиста `/register`  
2. Создать комнату в `/app`  
3. Открыть ссылку `/r/:slug` (клиент) или «Войти как ведущий»  
4. Разрешить камеру/микрофон  

## Структура

```
apps/web      — клиент
apps/api      — backend
infra/        — docker-compose, livekit.yaml
prototype     — (старый UI в корне: index.php)
```

## Запись

Ведущий в комнате: **● Запись** / **■ Стоп**. API:

- `POST /api/rooms/:slug/meetings/:meetingId/recording/start`
- `POST /api/rooms/:slug/meetings/:meetingId/recording/stop`

Файлы в MinIO Console: http://localhost:9001 (`evoroom` / `evoroomsecret`), бакет `evoroom`.

> Room Composite тяжёлый (Chrome внутри egress). На VDS 2 CPU запись может быть медленной или падать по памяти — смотрите `docker logs evoroom-egress`.

## Дальше

1. Пауза записи + список/скачивание в ЛК  
2. Чат  
3. Холст + Yjs  
4. Модули (карты, расстановки, рисование, PDF)  
5. Бьюти-фон (MediaPipe)  
6. Архив записей 30 дней → cold storage  
7. HTTPS / домен  
