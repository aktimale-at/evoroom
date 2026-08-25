# Evoroom

Психологическая студия: **видео (LiveKit) + холст + ЛК специалистов**, OSS, self-host в РФ.

Прототип UI: `index.php` (не production).  
ТЗ: `TZ-v2.md`.

## Стек

- **web** — React + TypeScript + Vite + LiveKit Client  
- **api** — NestJS + Prisma + PostgreSQL + JWT  
- **infra** — Docker: Postgres, Redis, MinIO, LiveKit  

Запись composite (Egress) — следующий вертикальный шаг вместе с видео.

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

Поднимет Postgres, Redis, MinIO, LiveKit.

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

## Дальше

1. LiveKit Egress — запись start/pause/stop + MinIO  
2. Чат  
3. Холст + Yjs  
4. Модули (карты, расстановки, рисование, PDF)  
5. Бьюти-фон (MediaPipe)  
6. Архив записей 30 дней → cold storage  
