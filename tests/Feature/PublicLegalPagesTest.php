<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLegalPagesTest extends TestCase
{
    public function test_public_legal_pages_do_not_require_login(): void
    {
        $this->get('/polityka-prywatnosci')->assertOk();
        $this->get('/rodo')->assertOk();
        $this->get('/regulamin')->assertOk();
    }
}
