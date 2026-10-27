<?php

namespace Tests\Unit;

use App\Support\SafeFilename;
use PHPUnit\Framework\TestCase;

class SafeFilenameTest extends TestCase
{
    public function test_sanitize(): void
    {
        $this->assertSame('laudo.pdf', SafeFilename::sanitize('laudo.pdf', 'pdf'));
        $this->assertSame('Laudo.pdf', SafeFilename::sanitize('Laudo.PDF', 'pdf'));
        $this->assertSame('passwd.pdf', SafeFilename::sanitize('../../etc/passwd', 'pdf'));
        $this->assertSame('evil.pdf', SafeFilename::sanitize('C:\\Users\\x\\evil.pdf', 'pdf'));
        $this->assertSame('pagina.html.pdf', SafeFilename::sanitize('pagina.html', 'pdf'));
        $this->assertSame('foto.jpeg', SafeFilename::sanitize('foto.jpeg', 'jpg'));
        $this->assertSame('foto.jpg.png', SafeFilename::sanitize('foto.jpg', 'png'));
        $this->assertSame('Exame de Visão.pdf', SafeFilename::sanitize("  Exame\tde\x00 Visão<>:*?|%.pdf  ", 'pdf'));
        $this->assertSame('anexo.pdf', SafeFilename::sanitize('...', 'pdf'));
        $this->assertSame('png.png', SafeFilename::sanitize('.png', 'png')); // leading dots are trimmed: never a hidden file
        $this->assertSame('anexo.pdf', SafeFilename::sanitize("\xC3\x28invalid", 'pdf'));
        $this->assertSame(str_repeat('a', 150).'.pdf', SafeFilename::sanitize(str_repeat('a', 300).'.pdf', 'pdf'));
    }

    public function test_disposition_has_ascii_fallback_and_utf8_name(): void
    {
        $this->assertSame(
            'attachment; filename="laudo.pdf"; filename*=UTF-8\'\'laudo.pdf',
            SafeFilename::disposition('laudo.pdf')
        );
        $this->assertSame(
            'attachment; filename="Exame de Visao (1).pdf"; filename*=UTF-8\'\'Exame%20de%20Vis%C3%A3o%20%281%29.pdf',
            SafeFilename::disposition('Exame de Visão (1).pdf')
        );
        $this->assertSame('attachment; filename="anexo.pdf"; filename*=UTF-8\'\'%E6%A4%9C%E6%9F%BB.pdf', SafeFilename::disposition('検査.pdf'));
    }
}
