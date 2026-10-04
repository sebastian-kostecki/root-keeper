<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SidePanelDemoController extends Controller
{
    /**
     * Demo of the shared side panel shell; local environment only, no database.
     */
    public function __invoke(Request $request): Response
    {
        abort_unless(app()->isLocal(), 404);

        $items = $this->items();

        return Inertia::render('dev/side-panel', [
            'items' => $items,
            'demoPanel' => function () use ($request, $items): ?array {
                $id = $request->query('demo');

                if (! is_string($id) || ! ctype_digit($id)) {
                    return null;
                }

                foreach ($items as $item) {
                    if ($item['id'] === (int) $id) {
                        return $item;
                    }
                }

                abort(404);
            },
        ]);
    }

    /**
     * @return list<array{id: int, title: string, body: string, wide: bool}>
     */
    private function items(): array
    {
        $long = str_repeat('Akt chrztu zapisany w księdze parafialnej, z adnotacją na marginesie o późniejszym ślubie. ', 25);

        return [
            ['id' => 1, 'title' => 'Jan Kowalski', 'body' => 'Krótki opis rekordu w panelu standardowym.', 'wide' => false],
            ['id' => 2, 'title' => 'Księga chrztów 1850', 'body' => 'Szeroki panel, np. skan obok formularza.', 'wide' => true],
            ['id' => 3, 'title' => 'Anna Nowak', 'body' => $long, 'wide' => false],
            ['id' => 4, 'title' => 'Parafia św. Michała', 'body' => 'Rekord z polem na niezapisane zmiany.', 'wide' => false],
            ['id' => 5, 'title' => 'Małżeństwo Kowalski i Nowak', 'body' => $long, 'wide' => true],
        ];
    }
}
