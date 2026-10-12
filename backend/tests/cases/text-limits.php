<?php
declare(strict_types=1);

// 4 bytes por carácter: el peor caso de UTF-8 (un TEXT admite 65 535 bytes, no caracteres).
$emoji = fn (int $n) => str_repeat("\u{1F422}", $n);

return [
    'límites: la descripción de una tarea de 65 000 caracteres multibyte se guarda completa' => function () use ($emoji) {
        $c = Suite::client();
        $p = Suite::project($c);
        $t = Suite::task($c, $p['id'], ['description' => $emoji(65000)]);
        Suite::same(65000, mb_strlen($t['description']), 'longitud guardada');
        Suite::status(422, $c->post("/api/projects/{$p['id']}/tasks", ['title' => 'x', 'description' => $emoji(65001)]), 'un carácter de más');
    },
    'límites: la descripción de un proyecto de 65 000 caracteres multibyte se guarda completa' => function () use ($emoji) {
        $c = Suite::client();
        $r = $c->post('/api/projects', ['name' => 'Largo', 'description' => $emoji(65000)]);
        Suite::status(200, $r);
        Suite::same(65000, mb_strlen($r['body']['description']), 'longitud guardada');
    },
    'límites: un checklist completo (100 × 250 caracteres multibyte) se guarda' => function () use ($emoji) {
        $c = Suite::client();
        $p = Suite::project($c);
        $items = array_fill(0, 100, ['text' => $emoji(250), 'done' => true]);
        $t = Suite::task($c, $p['id'], ['checklist' => $items]);
        Suite::same(100, count($t['checklist']), 'elementos');
        Suite::same(250, mb_strlen($t['checklist'][99]['text']), 'longitud del último');
    },
];
