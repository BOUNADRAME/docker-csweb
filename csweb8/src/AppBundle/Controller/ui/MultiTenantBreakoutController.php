<?php

namespace AppBundle\Controller\ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Service\PdoHelper;
use Psr\Log\LoggerInterface;

#[Route('/admin/multi-tenant/breakout')]
class MultiTenantBreakoutController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PdoHelper $pdo,
        private LoggerInterface $logger
    ) {}

    #[Route('/', name: 'mt_breakout_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Récupérer toutes les organisations avec leurs connexions
        $sql = "
            SELECT
                o.id as org_id,
                o.organization_code,
                o.organization_name,
                COUNT(DISTINCT c.id) as total_connections,
                COUNT(DISTINCT cj.id) as total_cron_jobs,
                COUNT(DISTINCT CASE WHEN cj.is_active = 1 THEN cj.id END) as active_jobs
            FROM mt_organizations o
            LEFT JOIN mt_database_connections c ON c.organization_id = o.id AND c.is_active = 1
            LEFT JOIN mt_cron_jobs cj ON cj.organization_id = o.id
            WHERE o.is_active = 1
            GROUP BY o.id, o.organization_code, o.organization_name
            ORDER BY o.organization_name
        ";

        $organizations = $this->pdo->fetchAll($sql);

        // Récupérer les jobs en cours d'exécution
        $sqlRunning = "
            SELECT
                cj.*,
                o.organization_name,
                c.connection_name
            FROM mt_cron_jobs cj
            JOIN mt_organizations o ON o.id = cj.organization_id
            LEFT JOIN mt_database_connections c ON c.id = cj.db_connection_id
            WHERE cj.last_run_status = 'running'
            ORDER BY cj.last_run_at DESC
        ";

        $runningJobs = $this->pdo->fetchAll($sqlRunning);

        return $this->render('multi_tenant/breakout/dashboard.html.twig', [
            'organizations' => $organizations,
            'running_jobs' => $runningJobs
        ]);
    }

    #[Route('/organization/{id}', name: 'mt_breakout_organization', methods: ['GET'])]
    public function organizationBreakout(int $id): Response
    {
        // Récupérer l'organisation
        $sqlOrg = "SELECT * FROM mt_organizations WHERE id = :id";
        $organization = $this->pdo->fetchAssoc($sqlOrg, ['id' => $id]);

        if (!$organization) {
            throw $this->createNotFoundException('Organisation non trouvée');
        }

        // Récupérer les connexions de cette organisation
        $sqlConnections = "
            SELECT * FROM mt_database_connections
            WHERE organization_id = :org_id AND is_active = 1
            ORDER BY is_default DESC, connection_name ASC
        ";
        $connections = $this->pdo->fetchAll($sqlConnections, ['org_id' => $id]);

        // Récupérer les dictionnaires disponibles
        $sqlDicts = "
            SELECT id, dictionary_name, dictionary_label
            FROM cspro_dictionaries
            ORDER BY dictionary_name
        ";
        $dictionaries = $this->pdo->fetchAll($sqlDicts);

        // Récupérer les cron jobs de cette organisation
        $sqlJobs = "
            SELECT
                cj.*,
                c.connection_name,
                c.db_driver
            FROM mt_cron_jobs cj
            LEFT JOIN mt_database_connections c ON c.id = cj.db_connection_id
            WHERE cj.organization_id = :org_id
            ORDER BY cj.is_active DESC, cj.created_time DESC
        ";
        $cronJobs = $this->pdo->fetchAll($sqlJobs, ['org_id' => $id]);

        return $this->render('multi_tenant/breakout/organization.html.twig', [
            'organization' => $organization,
            'connections' => $connections,
            'dictionaries' => $dictionaries,
            'cron_jobs' => $cronJobs
        ]);
    }

    #[Route('/cron/create/{orgId}', name: 'mt_cron_create', methods: ['GET', 'POST'])]
    public function createCronJob(Request $request, int $orgId): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $sql = "
                INSERT INTO mt_cron_jobs (
                    organization_id, job_name, job_type, command, cron_expression,
                    dictionaries, db_connection_id, is_active, created_time, modified_time
                ) VALUES (
                    :org_id, :job_name, :job_type, :command, :cron_expression,
                    :dictionaries, :db_connection_id, :is_active, NOW(), NOW()
                )
            ";

            $dictionaries = isset($data['dictionaries']) ? json_encode($data['dictionaries']) : null;

            $params = [
                'org_id' => $orgId,
                'job_name' => $data['job_name'],
                'job_type' => $data['job_type'] ?? 'breakout',
                'command' => $this->buildCommand($data),
                'cron_expression' => $data['cron_expression'],
                'dictionaries' => $dictionaries,
                'db_connection_id' => $data['db_connection_id'] ?? null,
                'is_active' => isset($data['is_active']) ? 1 : 0
            ];

            $this->pdo->executeUpdate($sql, $params);

            $this->addFlash('success', 'Cron job créé avec succès');
            return $this->redirectToRoute('mt_breakout_organization', ['id' => $orgId]);
        }

        // GET - Afficher le formulaire
        $sqlOrg = "SELECT * FROM mt_organizations WHERE id = :id";
        $organization = $this->pdo->fetchAssoc($sqlOrg, ['id' => $orgId]);

        $sqlConnections = "
            SELECT * FROM mt_database_connections
            WHERE organization_id = :org_id AND is_active = 1
        ";
        $connections = $this->pdo->fetchAll($sqlConnections, ['org_id' => $orgId]);

        $sqlDicts = "SELECT id, dictionary_name, dictionary_label FROM cspro_dictionaries";
        $dictionaries = $this->pdo->fetchAll($sqlDicts);

        return $this->render('multi_tenant/breakout/cron_form.html.twig', [
            'organization' => $organization,
            'connections' => $connections,
            'dictionaries' => $dictionaries,
            'cron_job' => null
        ]);
    }

    #[Route('/cron/toggle/{id}', name: 'mt_cron_toggle', methods: ['POST'])]
    public function toggleCronJob(int $id): JsonResponse
    {
        $sql = "UPDATE mt_cron_jobs SET is_active = NOT is_active, modified_time = NOW() WHERE id = :id";
        $this->pdo->executeUpdate($sql, ['id' => $id]);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/cron/delete/{id}', name: 'mt_cron_delete', methods: ['POST'])]
    public function deleteCronJob(int $id): JsonResponse
    {
        $sql = "DELETE FROM mt_cron_jobs WHERE id = :id";
        $this->pdo->executeUpdate($sql, ['id' => $id]);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/cron/run/{id}', name: 'mt_cron_run', methods: ['POST'])]
    public function runCronJob(int $id): JsonResponse
    {
        $sql = "SELECT * FROM mt_cron_jobs WHERE id = :id";
        $job = $this->pdo->fetchAssoc($sql, ['id' => $id]);

        if (!$job) {
            return new JsonResponse(['success' => false, 'error' => 'Job not found'], 404);
        }

        // Marquer comme en cours
        $updateSql = "UPDATE mt_cron_jobs SET last_run_status = 'running', last_run_at = NOW() WHERE id = :id";
        $this->pdo->executeUpdate($updateSql, ['id' => $id]);

        try {
            // Exécuter la commande en arrière-plan
            $command = $job['command'];
            exec($command . ' > /dev/null 2>&1 &');

            return new JsonResponse([
                'success' => true,
                'message' => 'Job lancé en arrière-plan'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error running cron job: ' . $e->getMessage());

            $updateSql = "UPDATE mt_cron_jobs SET last_run_status = 'failed', last_run_output = :output WHERE id = :id";
            $this->pdo->executeUpdate($updateSql, [
                'id' => $id,
                'output' => $e->getMessage()
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function buildCommand(array $data): string
    {
        $command = 'php /var/www/html/bin/console ';

        if ($data['job_type'] === 'breakout') {
            $command .= 'csweb:process-cases-by-dict';

            if (isset($data['threads'])) {
                $command .= ' --threads=' . $data['threads'];
            }

            if (isset($data['max_cases'])) {
                $command .= ' --maxCasesPerChunk=' . $data['max_cases'];
            }

            if (isset($data['dictionaries']) && is_array($data['dictionaries'])) {
                $command .= ' ' . implode(' ', $data['dictionaries']);
            }
        } else {
            $command .= $data['custom_command'] ?? '';
        }

        return $command;
    }
}
