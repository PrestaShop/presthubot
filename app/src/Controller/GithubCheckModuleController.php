<?php

namespace App\Controller;

use App\Presenter\ModuleCheck\Web\ModuleCheckPresenterWeb;
use App\Service\Command\CheckModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GithubCheckModuleController extends AbstractController
{
    #[Route('/module/check', name: 'app_module_check')]
    public function index(
        Request $request,
        CheckModule $checkModule,
        ModuleCheckPresenterWeb $moduleCheckWebPresenter,
    ): Response {
        $mode = $request->request->get('mode') ?? 'noAction';
        $module = null;
        if ('module' === $mode) {
            $module = $request->request->get('module');
        }

        $branch = '';
        $from = 1;
        $numberOfItems = 100;

        $modules = $checkModule->getRepositories();
        $lines = [];
        if ('noAction' !== $mode) {
            foreach (
                $checkModule->getCheckedRepositories(
                    $module,
                    $branch,
                    $from,
                    $numberOfItems,
                ) as $key => $repository) {
                if ($repository) {
                    $moduleCheckWebPresenter->present($repository);
                    $lines[] = $moduleCheckWebPresenter->viewModel;
                }
            }
        }

        return $this->render(
            'module/check.html.twig',
            [
                'lines' => $lines,
                'modules' => $modules,
            ]
        );
    }
}
