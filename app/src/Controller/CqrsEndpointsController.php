<?php

namespace App\Controller;

use App\Presenter\CqrsEndpoints\Web\CqrsEndpointsPresenterWeb;
use App\Service\Command\CqrsEndpoints;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CqrsEndpointsController extends AbstractController
{
    #[Route('/cqrs/endpoints', name: 'app_cqrs_endpoints')]
    public function index(
        Request $request,
        CqrsEndpoints $cqrsEndpoints,
        CqrsEndpointsPresenterWeb $cqrsEndpointsPresenterWeb,
    ): Response {
        $lines = [];
        foreach ($cqrsEndpoints->getEndpoints() as $endpoint) {
            $cqrsEndpointsPresenterWeb->present($endpoint);
            $lines[] = $cqrsEndpointsPresenterWeb->viewModel;
        }

        return $this->render(
            'cqrs/endpoints.html.twig',
            [
                'lines' => $lines,
            ]
        );
    }
}
