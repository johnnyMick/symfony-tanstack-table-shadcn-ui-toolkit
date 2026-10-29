<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use <?= $seeder_fqcn; ?>;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP reset endpoint used by the Settings → Demo data form.
 * Synced with the bundle demo ({@code DemoController::reset}).
 * Available in {@code dev} / {@code test} only — prefer CLI {@code app:demo:reset} in prod.
 * Prefer {@code make:tstdt-demo-dashboard --with-http-reset} to merge into DemoController instead.
 */
final class <?= $class_name; ?> extends AbstractController
{
    /**
     * Local/demo only — disabled in prod (schema wipe). Prefer CLI: app:demo:reset.
     */
    #[Route('/demo/reset', name: 'app_demo_reset', methods: ['POST'], env: ['dev', 'test'])]
    public function reset(Request $request, <?= $seeder_short; ?> $seeder): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('demo_reset', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $counts = $seeder->reset();
        $this->addFlash('success', \sprintf(
            'Demo data reset: %s.',
            implode(', ', array_map(
                static fn (string $table, int $count): string => \sprintf('%s=%s', $table, number_format($count)),
                array_keys($counts),
                array_values($counts),
            )),
        ));

        return $this->redirectToRoute('<?= $redirect_route; ?>');
    }
}
