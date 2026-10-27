<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Agendamentos (eram os crons do sistema antigo). Um único cron no servidor:
 *   * * * * * cd /u01/home/marlon/www/seguradora && php artisan schedule:run >> /dev/null 2>&1
 * Logs de cada comando em storage/logs/<comando>.log.
 */

// Fila da API das seguradoras: 4 vagas em paralelo (cada uma olha a fila por
// ~55s; a vaga é travada por arquivo dentro do comando — sem vaga livre, sai).
for ($vaga = 1; $vaga <= 4; $vaga++) {
    Schedule::command('api:fila')
        ->everyMinute()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/api-fila.log'));
}

// Reconsulta das seguradoras (cotações em análise, apólices em emissão, PDFs).
Schedule::command('api:acompanhamento')
    ->everyTwoMinutes()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/api-acompanhamento.log'));

// Segimob (Seguro Fiança): reconsulta as seguradoras, reenvia ficha que falhou
// e baixa os PDFs. O próprio comando trava por arquivo e para antes do minuto virar.
Schedule::command('segimob:acompanhamento')
    ->everyMinute()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/segimob-acompanhamento.log'));

// Leads novos do site → sino da MPS.
Schedule::command('leads:avisos')
    ->everyMinute()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/leads-site-avisos.log'));

// Prazos das tarefas (prazo em 3 dias / atraso) — uma vez por dia.
Schedule::command('tarefas:avisos')
    ->dailyAt('08:00')
    ->appendOutputTo(storage_path('logs/tarefas-avisos.log'));
