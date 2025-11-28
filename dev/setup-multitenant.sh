#!/bin/bash

# Script de démarrage rapide CSWeb Multi-Tenant
# Usage: ./setup-multitenant.sh

set -e

echo "========================================="
echo "  CSWeb Multi-Tenant - Setup Rapide"
echo "========================================="
echo ""

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Vérifier Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker n'est pas installé${NC}"
    echo "Installer Docker depuis: https://docs.docker.com/install/"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}❌ Docker Compose n'est pas installé${NC}"
    echo "Installer Docker Compose depuis: https://docs.docker.com/compose/install/"
    exit 1
fi

echo -e "${GREEN}✓ Docker et Docker Compose détectés${NC}"
echo ""

# Vérifier le fichier .env
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}⚠ Fichier .env non trouvé${NC}"
    echo "Veuillez copier et configurer le fichier .env:"
    echo "  cp .env.example .env"
    echo "  nano .env"
    exit 1
fi

echo -e "${GREEN}✓ Fichier .env détecté${NC}"
echo ""

# Arrêter les conteneurs existants
echo "📦 Arrêt des conteneurs existants..."
docker-compose -f docker-compose-multitenant.yml down 2>/dev/null || true

# Démarrer les conteneurs
echo "🚀 Démarrage des conteneurs..."
docker-compose -f docker-compose-multitenant.yml up -d

# Attendre que les services soient prêts
echo "⏳ Attente du démarrage des services..."
sleep 10

# Vérifier MySQL
echo "🔍 Vérification de MySQL..."
until docker exec csweb_mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
    echo "   Attente de MySQL..."
    sleep 2
done
echo -e "${GREEN}✓ MySQL est prêt${NC}"

# Vérifier PostgreSQL
echo "🔍 Vérification de PostgreSQL..."
until docker exec csweb_postgres pg_isready -U cspro 2>/dev/null; do
    echo "   Attente de PostgreSQL..."
    sleep 2
done
echo -e "${GREEN}✓ PostgreSQL est prêt${NC}"

echo ""
echo "========================================="
echo "  Configuration CSWeb"
echo "========================================="
echo ""
echo "1. Ouvrir votre navigateur: ${GREEN}http://localhost${NC}"
echo ""
echo "2. Compléter la configuration avec ces paramètres:"
echo "   Database name:        cspro"
echo "   Hostname:             mysql"
echo "   Database username:    cspro"
echo "   Database password:    (voir DB_PASSWORD dans .env)"
echo "   CSWeb admin password: (choisir un mot de passe sécurisé)"
echo "   Path to files:        /var/www/html/files"
echo "   CSWeb API URL:        http://localhost/api"
echo ""

read -p "Appuyez sur Entrée après avoir complété la configuration CSWeb..."

echo ""
echo "========================================="
echo "  Migration Multi-Tenant"
echo "========================================="
echo ""
echo "🔄 Exécution de la migration..."

docker exec -it csweb_php php bin/console doctrine:migrations:migrate --no-interaction

echo -e "${GREEN}✓ Migration terminée${NC}"

echo ""
echo "========================================="
echo "  Création Organisation Exemple"
echo "========================================="
echo ""

read -p "Voulez-vous créer une organisation exemple? (y/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📋 Création de l'organisation ANSD..."

    docker exec -it csweb_php php bin/console csweb:org:create \
        --code=ANSD \
        --name="ANSD Sénégal" \
        --type=statistics_office \
        --country=SN \
        --email=contact@ansd.sn

    echo ""
    echo "📊 Création de la connexion PostgreSQL..."

    docker exec -it csweb_php php bin/console csweb:db:add \
        --org=ANSD \
        --name="POSTGRES_PROD" \
        --driver=pdo_pgsql \
        --host=postgres \
        --port=5432 \
        --db=ansd_data \
        --user=cspro \
        --password=postgres_password \
        --default \
        --test

    echo ""
    echo "🗄️  Création de la base de données ansd_data..."

    docker exec -it csweb_postgres psql -U cspro -d cspro_data -c "CREATE DATABASE ansd_data;" 2>/dev/null || echo "Base déjà existante"
    docker exec -it csweb_postgres psql -U cspro -d cspro_data -c "GRANT ALL PRIVILEGES ON DATABASE ansd_data TO cspro;"

    echo -e "${GREEN}✓ Organisation ANSD créée avec succès${NC}"
fi

echo ""
echo "========================================="
echo "  Installation Terminée ! 🎉"
echo "========================================="
echo ""
echo "📍 Accès aux services:"
echo "   CSWeb:         ${GREEN}http://localhost${NC}"
echo "   Admin Panel:   ${GREEN}http://localhost/admin/multi-tenant${NC}"
echo "   phpMyAdmin:    ${GREEN}http://localhost:8080${NC}"
echo "   pgAdmin:       ${GREEN}http://localhost:8081${NC}"
echo ""
echo "📚 Documentation complète: ${GREEN}docs/README.md${NC}"
echo ""
echo "🔧 Commandes utiles:"
echo "   docker-compose -f docker-compose-multitenant.yml logs -f"
echo "   docker exec -it csweb_php bash"
echo "   docker exec -it csweb_postgres psql -U cspro"
echo ""
echo "🆘 Besoin d'aide? Consultez la documentation ou créez une issue sur GitHub"
echo ""
