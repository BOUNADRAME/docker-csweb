<?php

namespace AppBundle\Controller\ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Entity\Organization;
use AppBundle\Entity\DatabaseConnection;
use AppBundle\Service\DatabaseConnectionManager;

/**
 * Interface web d'administration pour le Super Admin
 *
 * @Route("/admin/multi-tenant", name="superadmin_")
 */
class SuperAdminController extends AbstractController
{
    /**
     * Dashboard principal
     *
     * @Route("/", name="dashboard")
     */
    public function dashboardAction(EntityManagerInterface $em)
    {

        $stats = [
            'total_organizations' => $em->getRepository(Organization::class)->count([]),
            'active_organizations' => $em->getRepository(Organization::class)->count(['isActive' => true]),
            'total_connections' => $em->getRepository(DatabaseConnection::class)->count([]),
            'active_connections' => $em->getRepository(DatabaseConnection::class)->count(['isActive' => true]),
        ];

        $organizations = $em->getRepository(Organization::class)->findBy([], ['organizationName' => 'ASC'], 10);

        return $this->render('superadmin/dashboard.html.twig', [
            'stats' => $stats,
            'organizations' => $organizations
        ]);
    }

    /**
     * Liste des organisations
     *
     * @Route("/organizations", name="organizations_list")
     */
    public function organizationsAction(EntityManagerInterface $em)
    {
        $organizations = $em->getRepository(Organization::class)->findBy([], ['organizationName' => 'ASC']);

        return $this->render('superadmin/organizations_list.html.twig', [
            'organizations' => $organizations
        ]);
    }

    /**
     * Créer une organisation
     *
     * @Route("/organizations/create", name="organizations_create", methods={"GET", "POST"})
     */
    public function createOrganizationAction(Request $request, EntityManagerInterface $em)
    {
        if ($request->isMethod('POST')) {

            $organization = new Organization();
            $organization->setOrganizationCode($request->request->get('code'));
            $organization->setOrganizationName($request->request->get('name'));
            $organization->setOrganizationType($request->request->get('type'));
            $organization->setCountryCode($request->request->get('country'));
            $organization->setContactEmail($request->request->get('email'));

            $em->persist($organization);
            $em->flush();

            $this->addFlash('success', 'Organisation créée avec succès !');

            return $this->redirectToRoute('superadmin_organizations_list');
        }

        return $this->render('superadmin/organization_form.html.twig', [
            'organization' => null
        ]);
    }

    /**
     * Éditer une organisation
     *
     * @Route("/organizations/{id}/edit", name="organizations_edit", methods={"GET", "POST"})
     */
    public function editOrganizationAction(Request $request, $id, EntityManagerInterface $em)
    {
        $organization = $em->getRepository(Organization::class)->find($id);

        if (!$organization) {
            throw $this->createNotFoundException('Organisation non trouvée');
        }

        if ($request->isMethod('POST')) {
            $organization->setOrganizationName($request->request->get('name'));
            $organization->setOrganizationType($request->request->get('type'));
            $organization->setCountryCode($request->request->get('country'));
            $organization->setContactEmail($request->request->get('email'));
            $organization->setIsActive($request->request->get('is_active') === '1');

            $em->flush();

            $this->addFlash('success', 'Organisation mise à jour avec succès !');

            return $this->redirectToRoute('superadmin_organizations_list');
        }

        return $this->render('superadmin/organization_form.html.twig', [
            'organization' => $organization
        ]);
    }

    /**
     * Connexions de base de données d'une organisation
     *
     * @Route("/organizations/{id}/connections", name="organization_connections")
     */
    public function organizationConnectionsAction($id, EntityManagerInterface $em)
    {
        $organization = $em->getRepository(Organization::class)->find($id);

        if (!$organization) {
            throw $this->createNotFoundException('Organisation non trouvée');
        }

        $connections = $em->getRepository(DatabaseConnection::class)->findBy(
            ['organization' => $organization],
            ['isDefault' => 'DESC', 'connectionName' => 'ASC']
        );

        return $this->render('superadmin/connections_list.html.twig', [
            'organization' => $organization,
            'connections' => $connections
        ]);
    }

    /**
     * Créer une connexion de base de données
     *
     * @Route("/organizations/{orgId}/connections/create", name="connections_create", methods={"GET", "POST"})
     */
    public function createConnectionAction(Request $request, $orgId, EntityManagerInterface $em, DatabaseConnectionManager $connectionManager)
    {
        $organization = $em->getRepository(Organization::class)->find($orgId);

        if (!$organization) {
            throw $this->createNotFoundException('Organisation non trouvée');
        }

        if ($request->isMethod('POST')) {
            $connection = new DatabaseConnection();
            $connection->setOrganization($organization);
            $connection->setConnectionName($request->request->get('name'));
            $connection->setDbDriver($request->request->get('driver'));
            $connection->setDbHost($request->request->get('host'));
            $connection->setDbPort($request->request->get('port') ?: null);
            $connection->setDbName($request->request->get('database'));
            $connection->setDbUser($request->request->get('user'));
            $connection->setDbCharset($request->request->get('charset') ?: 'utf8mb4');

            // Chiffrer le mot de passe
            $password = $request->request->get('password');
            $encryptedPassword = $connectionManager->encryptPassword($password);
            $connection->setDbPasswordEncrypted($encryptedPassword);

            // Définir comme connexion par défaut si demandé
            if ($request->request->get('is_default') === '1') {
                // Désactiver les autres connexions par défaut
                $otherDefaults = $em->getRepository(DatabaseConnection::class)->findBy([
                    'organization' => $organization,
                    'isDefault' => true
                ]);
                foreach ($otherDefaults as $other) {
                    $other->setIsDefault(false);
                }
                $connection->setIsDefault(true);
            }

            // Tester la connexion
            $testResult = $connectionManager->testConnection($connection);

            if ($testResult['success']) {
                $em->persist($connection);
                $em->flush();

                $this->addFlash('success', 'Connexion créée et testée avec succès !');
                return $this->redirectToRoute('superadmin_organization_connections', ['id' => $orgId]);
            } else {
                $this->addFlash('error', 'Test de connexion échoué : ' . $testResult['message']);
            }
        }

        return $this->render('superadmin/connection_form.html.twig', [
            'organization' => $organization,
            'connection' => null
        ]);
    }

    /**
     * Tester une connexion
     *
     * @Route("/connections/{id}/test", name="connections_test", methods={"POST"})
     */
    public function testConnectionAction($id, EntityManagerInterface $em, DatabaseConnectionManager $connectionManager)
    {
        $connection = $em->getRepository(DatabaseConnection::class)->find($id);

        if (!$connection) {
            return new JsonResponse(['success' => false, 'message' => 'Connexion non trouvée'], 404);
        }

        $result = $connectionManager->testConnection($connection);

        return new JsonResponse($result);
    }

    /**
     * Activer/Désactiver une connexion
     *
     * @Route("/connections/{id}/toggle", name="connections_toggle", methods={"POST"})
     */
    public function toggleConnectionAction($id, EntityManagerInterface $em)
    {
        $connection = $em->getRepository(DatabaseConnection::class)->find($id);

        if (!$connection) {
            return new JsonResponse(['success' => false, 'message' => 'Connexion non trouvée'], 404);
        }

        $connection->setIsActive(!$connection->isActive());
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'isActive' => $connection->isActive()
        ]);
    }

    /**
     * Supprimer une connexion
     *
     * @Route("/connections/{id}/delete", name="connections_delete", methods={"POST"})
     */
    public function deleteConnectionAction($id, EntityManagerInterface $em)
    {
        $connection = $em->getRepository(DatabaseConnection::class)->find($id);

        if (!$connection) {
            return new JsonResponse(['success' => false, 'message' => 'Connexion non trouvée'], 404);
        }

        $orgId = $connection->getOrganization()->getId();

        $em->remove($connection);
        $em->flush();

        $this->addFlash('success', 'Connexion supprimée avec succès');

        return $this->redirectToRoute('superadmin_organization_connections', ['id' => $orgId]);
    }
}
