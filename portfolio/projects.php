  <?php

    $projects = [
        [
            'title' => 'Controle Financeiro',
            'finished' => true,
            'year' => 2025,
            'description' => 'Projeto feito para realizar o controle das finanças',
            'stack' => ['PHP', 'APIATO', 'REACT', 'NEXT', 'LARAVEL']
        ],
        [
            'title' => 'Controle Financeiro',
            'finished' => true,
            'year' => 2025,
            'description' => 'Projeto feito para realizar o controle das finanças',
            'stack' => ['PHP', 'APIATO', 'REACT', 'NEXT', 'LARAVEL']
        ],
        [
            'title' => 'Controle Financeiro',
            'finished' => true,
            'year' => 2025,
            'description' => 'Projeto feito para realizar o controle das finanças',
            'stack' => ['PHP', 'APIATO', 'REACT', 'NEXT', 'LARAVEL']
        ],
    ];

    $stackColors = [
        'bg-purple-500 text-purple-900',
        'bg-yellow-500 text-yellow-900',
        'bg-red-500 text-red-900',
        'bg-cyan-500 text-cyan-900',
        'bg-blue-500 text-blue-900'
    ];

    ?>

  <!-- projects -->
  <section id="projects">
      <?php foreach ($projects as $project) : ?>
          <div class="flex items-center bg-slate-700 rounded-full overflow-hidden p-2 mt-10">
              <div class="w-2/10 text-center">Image</div>
              <div class="w-8/10 space-y-2">
                  <p class="font-bold text-xl"><?= $project['title']; ?> <span class="text-xs text-slate-500 align-middle"><?= $project['finished'] ?  '(finalizado)' : '(em andamento'; ?></span></p>
                  <div class="flex gap-2">
                      <?php foreach ($project['stack'] as $key => $stack) : ?>
                          <p class="px-2 text-center rounded-full <?= $stackColors[$key]; ?>   w-fit font-bold"><?= $stack; ?></p>
                      <?php endforeach; ?>
                  </div>
                  <p class="font-bold">Projeto feito para realizar o controle das finanças</p>
              </div>
          </div>
      <?php endforeach; ?>
  </section>