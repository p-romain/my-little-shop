<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontendController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/{reactRouting}', name: 'frontend', requirements: ['reactRouting' => '^(?!api|build|doc(?:\.json)?/?$).*$'], defaults: ['reactRouting' => null], methods: ['GET'])]
    public function __invoke(): Response
    {
        $html = file_get_contents($this->projectDir.'/public/index.html');

        foreach (['app.css', 'runtime.js', 'app.js'] as $asset) {
            $assetPath = $this->projectDir.'/public/build/'.$asset;
            $version = is_file($assetPath) ? (string) filemtime($assetPath) : 'dev';
            $html = str_replace('/build/'.$asset, '/build/'.$asset.'?v='.$version, $html);
        }

        return new Response(
            $html,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }
}
