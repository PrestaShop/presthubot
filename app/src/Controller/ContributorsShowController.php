<?php

namespace App\Controller;

use App\Presenter\Contributors\Web\ContributorsPresenterWeb;
use App\Presenter\RepositoryCheck\Web\RepositoryCheckPresenterWeb;
use App\Service\Command\CheckRepository;
use App\Service\Command\Contributors;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContributorsShowController extends AbstractController
{
    #[Route('/contributors/show', name: 'app_contributors_show')]
    public function index(
        Contributors             $contributorsService,
        ContributorsPresenterWeb $contributorsPresenterWeb,
    ): Response {
        $lines = [];
        $contributors = $contributorsService->getContributors();
        // contributors
        foreach ($contributorsService->getDetails($contributors) as $contributorsIssues) {
            $lines[] = $contributorsIssues;
            // issues
            foreach ($contributorsService->getIssue($contributorsIssues->issues, $contributorsIssues->author) as $issue) {
                $contributorsPresenterWeb->present($issue);
                $lines[] = $contributorsPresenterWeb->viewModel;
            }
        }

        return $this->render(
            'contributors/show.html.twig',
            [
                'lines' => $lines,
            ]
        );
    }
}
