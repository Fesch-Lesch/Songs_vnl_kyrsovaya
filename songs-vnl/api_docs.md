# API информационной системы SONGS.VNL
База: `http://localhost:3000/api.php`. Ответ всегда JSON: `{"ok":true|false, ...}`.

## 1. GET ?method=albums
Список альбомов витрины. Данные обработаны: склеены исполнитель+название (`name`),
жанр в верхнем регистре (`category`), цена аренды посчитана за неделю (`rent.week`),
наличие приведено к булеву (`available`).
Пример: `{"ok":true,"data":[{"id":1,"name":"Radiohead — In rainbows","category":"ROCK",
"media":"vinil","year":2007,"buy_price":3600,"rent":{"per_day":199,"week":1393},"available":true}]}`

## 2. GET ?method=album&id={id}
Карточка альбома + треклист. Ошибки: `404 {"ok":false,"error":"not found"}`.

## 3. GET ?method=notes
Список заметок. Обработка: `title_user` = заголовок + логин автора в скобках,
`formatted_date` = "дд.мм.гггг чч:мм".
Пример: `{"ok":true,"data":[{"id":1,"title_user":"Пополнение коллекции (admin)",
"formatted_date":"01.10.2026 10:12","body":"Приняли 20 пластинок 80-х..."}]}`

## 4. GET ?method=note&id={id}
Одна заметка в том же формате. Ошибки: `404`.

## 5. POST ?method=note_add
Создание заметки авторизованным пользователем (cookie сессии).
Тело JSON: `{"title":"...","body":"..."}`.
Ответ: `{"ok":true,"id":5}`. Ошибки: `403 forbidden` (нет сессии), `400 title required`.

## 6. Ошибки маршрутизации
`400 {"ok":false,"error":"unknown method"}` — неизвестный метод.