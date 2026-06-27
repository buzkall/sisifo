<?php

use Arzcode\Sisifo\Enums\MailboxTaskNotificationEnum;
use Arzcode\Sisifo\Enums\MailboxTaskTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_tasks', function(Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default(MailboxTaskTypeEnum::Summary->value);
            $table->text('prompt');
            $table->boolean('is_active')->default(true);
            $table->boolean('one_shot')->default(false);
            $table->string('schedule_frequency')->nullable();
            $table->string('schedule_time')->nullable();
            $table->string('schedule_timezone')->default('Europe/Madrid');
            $table->json('filters')->nullable();
            $table->string('notification_method')->default(MailboxTaskNotificationEnum::Pushover->value);
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_result')->nullable();
            $table->timestamps();
        });

        Schema::create('mailbox_task_inbound_email', function(Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_task_id')->constrained('mailbox_tasks')->cascadeOnDelete();
            $table->foreignId('inbound_email_id')->constrained('mailbox_inbound_emails')->cascadeOnDelete();
            $table->timestamp('processed_at');

            $table->unique(['mailbox_task_id', 'inbound_email_id'], 'task_email_unique');
        });

        $this->seedDefaultTask();

        $defaultTask = DB::table('mailbox_tasks')->where('name', 'Resumen diario')->first();

        if ($defaultTask && Schema::hasColumn('mailbox_inbound_emails', 'summarized')) {
            DB::table('mailbox_inbound_emails')
                ->where('summarized', true)
                ->orderBy('id')
                ->each(function($email) use ($defaultTask) {
                    DB::table('mailbox_task_inbound_email')->insert([
                        'mailbox_task_id'  => $defaultTask->id,
                        'inbound_email_id' => $email->id,
                        'processed_at'     => $email->updated_at ?? now(),
                    ]);
                });
        }

        if (Schema::hasColumn('mailbox_inbound_emails', 'summarized')) {
            Schema::table('mailbox_inbound_emails', function(Blueprint $table) {
                $table->dropColumn('summarized');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mailbox_inbound_emails', 'summarized')) {
            Schema::table('mailbox_inbound_emails', function(Blueprint $table) {
                $table->boolean('summarized')->default(false)->after('received_at');
            });
        }

        Schema::dropIfExists('mailbox_task_inbound_email');
        Schema::dropIfExists('mailbox_tasks');
    }

    private function seedDefaultTask(): void
    {
        $exists = DB::table('mailbox_tasks')->where('name', 'Resumen diario')->exists();

        if ($exists) {
            return;
        }

        DB::table('mailbox_tasks')->insert([
            'name'   => 'Resumen diario',
            'type'   => MailboxTaskTypeEnum::Summary->value,
            'prompt' => <<<'PROMPT'
            Eres un asistente de email. Tu tarea es resumir correos electrónicos en español.

            Reglas:
            - Agrupa los correos por remitente cuando sea posible. Agrupa también por cliente: Dictapp, Cudú, Mimotic, Chromatic
            - Destaca cualquier asunto urgente o que requiera acción.
            - Sé conciso: el resumen debe ser breve y directo (máximo 1024 caracteres).
            - Usa HTML simple para formato: <b>negrita</b> para remitentes y asuntos importantes, <i>cursiva</i> para énfasis.
            - Usa <br> para saltos de línea. No uses \n. Usa un único salto de línea cada vez. Añade un <hr/> entre clientes.
            - No uses markdown (**, ##, etc.). Solo HTML compatible con Pushover: <b>, <i>, <u>, <a href="">, <br>.
            - No incluyas saludos ni despedidas, ni firmas de correo. ve directo al contenido.
            PROMPT,
            'is_active'           => true,
            'one_shot'            => false,
            'schedule_frequency'  => 'weekdays',
            'schedule_time'       => '09:00',
            'schedule_timezone'   => 'Europe/Madrid',
            'notification_method' => MailboxTaskNotificationEnum::Pushover->value,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }
};
