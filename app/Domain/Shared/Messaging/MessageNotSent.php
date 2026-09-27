<?php

declare(strict_types=1);

namespace App\Domain\Shared\Messaging;

use RuntimeException;

/** Pesan gagal terkirim; pemanggil memakai jalur manual (A-273). */
class MessageNotSent extends RuntimeException {}
