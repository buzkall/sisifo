<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

class CreateMailboxSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mailbox.common_prompt', '- Destaca cualquier asunto urgente o que requiera acción.
- Sé conciso: el resumen debe ser breve y directo (máximo 1024 caracteres).
- Usa HTML simple para formato: <b>negrita</b> para remitentes y asuntos importantes, <i>cursiva</i> para énfasis.
- Usa <br> para saltos de línea. No uses \n. Usa un único salto de línea cada vez. Añade un <hr/> entre clientes.
- No uses markdown (**, ##, etc.). Solo HTML compatible con Pushover: <b>, <i>, <u>, <a href="">, <br>.
- No incluyas saludos ni despedidas, ni firmas de correo. ve directo al contenido.');
    }

    public function down(): void
    {
        $this->migrator->delete('mailbox.common_prompt');
    }
}
