## 11. Технологический стек (согласовано)

**Ограничения:** open source, self-host в РФ (Beget VDS / Yandex Cloud), без зарубежных SaaS как ядра.

### 11.1. Стек

| Слой | Технология |
|------|------------|
| Web | React + TypeScript + Vite |
| API | NestJS + Prisma |
| БД | PostgreSQL |
| Кэш/очереди | Redis |
| Файлы / записи | MinIO (hot 30 дней) → archive bucket |
| Видео SFU | LiveKit Server (self-host) |
| Запись | LiveKit Egress (composite: как видят участники) + FFmpeg |
| TURN | coturn |
| Холст sync | Yjs |
| Бьюти-фон | MediaPipe Tasks Vision (self-host model) |
| Auth | email/пароль или код на почту; клиент — ссылка + имя |

### 11.2. Продуктовые решения

- До 10 участников в комнате; старт 1–5 комнат.
- Видео: галерея + screen share; 720p default, 1080p в настройках ЛК.
- Запись: по кнопке, пауза/стоп, шаринг ссылки клиенту; 30 дней → архив.
- Видео и запись внедряются **одним вертикальным этапом**.
- Холст: по умолчанию контроль ведущего, опционально collaborative.
- Много специалистов = много личных ЛК (без орг/клиники в v1).
- Кабинет клиента — later (задел в модели данных).
- Чат — в scope v1.

### 11.3. Порядок внедрения

1. Auth + ЛК + комнаты + ссылка  
2. **Видео + запись composite** (LiveKit + Egress)  
3. Чат  
4. Холст + Yjs + права  
5. Модули  
6. Архив записей / шаринг polish  
7. Бьюти-фон  

### 11.4. Репозиторий

```
apps/web   — клиент
apps/api   — backend
infra/     — docker-compose (postgres, redis, minio, livekit, egress)
```

Прототип UX: `index.php` (не production).

---
