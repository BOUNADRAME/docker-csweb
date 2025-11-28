#!/bin/bash

# Script d'automatisation du déploiement CSWeb Multi-Tenant
# Ce script configure automatiquement toutes les fonctionnalités multi-tenant

set -e

# Couleurs pour l'affichage
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages
print_step() {
    echo -e "${BLUE}==>${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Vérifier que Docker est en cours d'exécution
print_step "Vérification de Docker..."
if ! docker info > /dev/null 2>&1; then
    print_error "Docker n'est pas en cours d'exécution. Veuillez démarrer Docker."
    exit 1
fi
print_success "Docker est actif"

# Vérifier que les conteneurs sont démarrés
print_step "Vérification des conteneurs..."
if ! docker ps | grep -q "php"; then
    print_error "Le conteneur PHP n'est pas démarré. Exécutez 'docker-compose up -d' d'abord."
    exit 1
fi
print_success "Conteneurs actifs"

# Créer les tables multi-tenant
print_step "Création des tables multi-tenant..."
docker exec mysql mysql -uroot -prootpassword cspro << 'EOF' 2>&1 | grep -v "Warning" || true
CREATE TABLE IF NOT EXISTS mt_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    admin_email VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mt_database_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    connection_name VARCHAR(100) NOT NULL,
    db_type VARCHAR(20) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    database_name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password_encrypted TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    last_tested_at DATETIME DEFAULT NULL,
    test_status VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (organization_id) REFERENCES mt_organizations(id) ON DELETE CASCADE,
    INDEX idx_org (organization_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mt_dict_org_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dictionary_name VARCHAR(100) NOT NULL,
    organization_id INT NOT NULL,
    db_connection_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (organization_id) REFERENCES mt_organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (db_connection_id) REFERENCES mt_database_connections(id) ON DELETE SET NULL,
    UNIQUE KEY unique_dict_org (dictionary_name, organization_id),
    INDEX idx_dict (dictionary_name),
    INDEX idx_org (organization_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF

print_success "Tables multi-tenant créées"

# Synchroniser les utilisateurs OAuth
print_step "Synchronisation des utilisateurs OAuth..."
docker exec mysql mysql -uroot -prootpassword cspro -e "
INSERT IGNORE INTO oauth_users (username, password, first_name, last_name)
SELECT username, password, 'User', 'CSWeb'
FROM cspro_users
WHERE username NOT IN (SELECT username FROM oauth_users);
" 2>&1 | grep -v "Warning" || true
print_success "Utilisateurs OAuth synchronisés"

# Vider le cache
print_step "Nettoyage du cache..."
docker exec -u root php bash -c "cd /var/www/html && rm -rf var/cache/*" 2>&1 || true
docker exec php bash -c "cd /var/www/html && php bin/console cache:warmup" 2>&1 | tail -2
docker exec -u root php bash -c "cd /var/www/html && chown -R www-data:www-data var/" 2>&1 || true
print_success "Cache nettoyé et régénéré"

# Vérifier les tables créées
print_step "Vérification des tables multi-tenant..."
TABLES=$(docker exec mysql mysql -uroot -prootpassword cspro -e "SHOW TABLES LIKE 'mt_%';" 2>/dev/null | tail -n +2 | wc -l)
if [ "$TABLES" -eq 3 ]; then
    print_success "Les 3 tables multi-tenant sont créées"
else
    print_warning "Seulement $TABLES/3 tables créées"
fi

# Afficher les routes multi-tenant
print_step "Routes multi-tenant disponibles:"
docker exec php php bin/console debug:router 2>/dev/null | grep "superadmin" | awk '{print "  " $1}' || true

echo ""
print_success "Configuration multi-tenant terminée!"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Interface Multi-Tenant${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  ${YELLOW}URL:${NC}       http://localhost/admin/multi-tenant"
echo -e "  ${YELLOW}Username:${NC}  admin"
echo -e "  ${YELLOW}Password:${NC}  admin"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${GREEN}Commandes CLI disponibles:${NC}"
echo ""
echo -e "  ${YELLOW}# Créer une organisation${NC}"
echo -e "  docker exec php php bin/console csweb:org:create --code=ANSD --name=\"ANSD Sénégal\""
echo ""
echo -e "  ${YELLOW}# Lister les organisations${NC}"
echo -e "  docker exec php php bin/console csweb:org:list"
echo ""
echo -e "  ${YELLOW}# Créer une connexion de base de données${NC}"
echo -e "  docker exec php php bin/console csweb:db:create --org=ANSD --name=\"PostgreSQL ANSD\" --type=pdo_pgsql --host=postgres --port=5432 --database=ansd_data --username=ansd --password=secret123"
echo ""
echo -e "  ${YELLOW}# Tester une connexion${NC}"
echo -e "  docker exec php php bin/console csweb:db:test 1"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
