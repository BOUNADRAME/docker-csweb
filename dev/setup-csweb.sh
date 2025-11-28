#!/bin/bash

# Script de setup automatique CSWeb Multi-Tenant
# Ce script automatise l'installation complète de CSWeb avec support multi-tenant

set -e

echo "======================================"
echo "  CSWeb Multi-Tenant - Setup Script  "
echo "======================================"
echo ""

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Fonction pour afficher des messages
info() {
    echo -e "${BLUE}ℹ${NC}  $1"
}

success() {
    echo -e "${GREEN}✓${NC}  $1"
}

warning() {
    echo -e "${YELLOW}⚠${NC}  $1"
}

error() {
    echo -e "${RED}✗${NC}  $1"
}

# Vérifier que Docker est installé
info "Vérification de Docker..."
if ! command -v docker &> /dev/null; then
    error "Docker n'est pas installé. Veuillez l'installer : https://docs.docker.com/install/"
    exit 1
fi
success "Docker est installé"

# Vérifier que Docker Compose est installé
info "Vérification de Docker Compose..."
if ! command -v docker-compose &> /dev/null; then
    error "Docker Compose n'est pas installé. Veuillez l'installer."
    exit 1
fi
success "Docker Compose est installé"

# Arrêter les conteneurs existants si nécessaire
info "Nettoyage des conteneurs existants..."
docker-compose down 2>/dev/null || true
success "Conteneurs arrêtés"

# Démarrer les conteneurs
info "Démarrage des conteneurs Docker..."
docker-compose up -d

# Attendre que les services soient prêts
info "Attente du démarrage de MySQL..."
sleep 10

# Vérifier que MySQL est prêt
MAX_ATTEMPTS=30
ATTEMPT=0
while ! docker exec mysql mysql -u root -p7rsvObokpoN9Cb6s -e "SELECT 1" &>/dev/null; do
    ATTEMPT=$((ATTEMPT+1))
    if [ $ATTEMPT -ge $MAX_ATTEMPTS ]; then
        error "MySQL n'a pas démarré après $MAX_ATTEMPTS tentatives"
        exit 1
    fi
    echo -n "."
    sleep 2
done
echo ""
success "MySQL est prêt"

# Vérifier que PHP est prêt
info "Attente du démarrage de PHP..."
sleep 5
success "PHP est prêt"

# Installer Composer dans le conteneur
info "Installation de Composer..."
docker exec php bash -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer" > /dev/null 2>&1
success "Composer installé"

# Installer les dépendances PHP
info "Installation des dépendances PHP (cela peut prendre quelques minutes)..."
docker exec php bash -c "cd /var/www/html && composer install --no-interaction --optimize-autoloader --no-dev 2>&1 | grep -E '(Loading|Installing|Package|Generating)'" || true
success "Dépendances PHP installées"

# Corriger les permissions
info "Correction des permissions..."
docker exec php bash -c "chown -R www-data:www-data /var/www/html/app /var/www/html/src /var/www/html/var /var/www/html/files /var/www/html/web 2>/dev/null" || true
success "Permissions corrigées"

# Nettoyer le cache
info "Nettoyage du cache Symfony..."
docker exec php bash -c "cd /var/www/html && rm -rf var/cache/*" 2>/dev/null || true
docker exec php bash -c "cd /var/www/html && composer dump-autoload --optimize --quiet" 2>/dev/null || true
success "Cache nettoyé"

# Configuration multi-tenant
info "Configuration multi-tenant..."
bash ./setup-multi-tenant.sh 2>&1 | tail -5
success "Multi-tenant configuré"

echo ""
echo -e "${GREEN}======================================"
echo "  Installation terminée avec succès!"
echo "======================================${NC}"
echo ""
echo "📋 Prochaines étapes :"
echo ""
echo "1. Ouvrir votre navigateur : ${BLUE}http://localhost${NC}"
echo ""
echo "2. Se connecter avec :"
echo "   - Username: ${YELLOW}admin${NC}"
echo "   - Password: ${YELLOW}admin${NC}"
echo ""
echo "3. Accéder à l'interface multi-tenant :"
echo "   ${BLUE}http://localhost/admin/multi-tenant${NC}"
echo ""
echo "4. Créer votre première organisation via CLI (optionnel) :"
echo "   ${BLUE}docker exec php php bin/console csweb:org:create --code=ANSD --name=\"ANSD Sénégal\"${NC}"
echo ""
echo "📊 Services disponibles :"
echo "   - CSWeb:      http://localhost"
echo "   - phpMyAdmin: http://localhost:8080 (root / 7rsvObokpoN9Cb6s)"
echo ""
echo "🛠️  Commandes utiles :"
echo "   - Voir les logs:     ${BLUE}docker-compose logs -f php${NC}"
echo "   - Entrer dans PHP:   ${BLUE}docker exec -it php bash${NC}"
echo "   - Arrêter:           ${BLUE}docker-compose stop${NC}"
echo "   - Redémarrer:        ${BLUE}docker-compose restart${NC}"
echo ""
