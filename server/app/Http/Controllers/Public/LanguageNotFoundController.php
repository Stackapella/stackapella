<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Any /<lang>/ address a language doesn't serve (App\Support\Languages\SiteLanguages): not found, never the app's
 * shell, so search never sees an English page or the app at a language's address.
 */
final class LanguageNotFoundController extends Controller
{
    public function __invoke(): never
    {
        throw new NotFoundHttpException;
    }
}
