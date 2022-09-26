<?php

namespace App\Controller;

use App\Presenter\RepositoryCheck\Web\RepositoryCheckPresenterWeb;
use App\Service\Command\CheckRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RepositoryCheckController extends AbstractController
{
    #[Route('/repository/check', name: 'app_repository_check')]
    public function index(
        Request $request,
        CheckRepository $checkRepository,
        RepositoryCheckPresenterWeb $repositoryCheckWebPresenter,
    ): Response {
        $branch = '';
        $from = 1;
        $numberOfItems = 100;

        foreach (
            $checkRepository->getCheckedRepositories(
                'Prestashop',
                null
            ) as $key => $repository) {
            if ($repository) {
                $repositoryCheckWebPresenter->present($repository);
                $lines[] = $repositoryCheckWebPresenter->viewModel;
            }
        }

        return $this->render(
            'repository/check.html.twig',
            [
                'lines' => $lines,
            ]
        );
    }
}
