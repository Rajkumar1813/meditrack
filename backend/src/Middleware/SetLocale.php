<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Container;
use MediTrack\Core\Lang;

class SetLocale implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $locale = $request->query('locale');

        if (!$locale) {
            $acceptLanguage = $request->header('accept-language', '');
            if (str_starts_with(strtolower($acceptLanguage), 'hi')) {
                $locale = 'hi';
            } else {
                $locale = 'en';
            }
        }

        $container = Container::getInstance();
        if ($container->has(Lang::class)) {
            /** @var Lang $lang */
            $lang = $container->get(Lang::class);
            $lang->setLocale($locale);
        }

        return $next($request);
    }
}

