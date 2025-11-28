# État de l'Installation CSWeb Multi-Tenant

## ✅ Étapes Complétées

### 1. Configuration Docker
- ✅ Dockerfile PHP mis à jour avec support multi-SGBD :
  - MySQL (pdo_mysql) - ACTIF
  - PostgreSQL (pdo_pgsql) - ACTIF
  - SQL Server : FreeTDS (pdo_odbc) pour ARM64 - INSTALLÉ (pas activé pour le moment)
- ✅ Docker Compose configuré avec :
  - Conteneur PHP (Apache + PHP)
  - Conteneur MySQL (base principale)
  - Conteneur phpMyAdmin
  - Réseaux : csweb_frontend, csweb_backend
  - Volume : csweb_mysql_data

### 2. Dépendances
- ✅ Composer installé dans le conteneur PHP
- ✅ Dépendances Symfony installées (vendor/autoload.php créé)
- ✅ Permissions fichiers corrigées pour www-data
- ✅ SuperAdminController mis à jour pour Symfony 5 (AbstractController)
- ✅ Cache Symfony nettoyé et autoload régénéré

### 3. Services Actifs
```bash
# Vérifier les conteneurs
docker ps

# Services disponibles :
# - CSWeb: http://localhost
# - phpMyAdmin: http://localhost:8080
```

## 🔄 Prochaines Étapes

### Étape 1 : Configuration Initiale de CSWeb (via Navigateur)

1. **Ouvrir le navigateur** : `http://localhost`

2. **Suivre l'assistant de configuration** :
   - Informations sur la base de données MySQL :
     - Host: `mysql`
     - Port: `3306`
     - Database: `cspro` (depuis .env: `${DB_NAME}`)
     - Username: `cspro` (depuis .env: `${DB_USERNAME}`)
     - Password: `cspro` (depuis .env: `${DB_PASSWORD}`)

   - Créer le compte super administrateur (noter ces identifiants !)

   - Configurer les paramètres de l'application

3. **Vérifier** que CSWeb fonctionne correctement

### Étape 2 : Migration Multi-Tenant

Une fois CSWeb configuré, exécuter la migration pour activer le multi-tenant :

```bash
# Entrer dans le conteneur PHP
docker exec -it php bash

# Exécuter les migrations Doctrine
php bin/console doctrine:migrations:migrate --no-interaction

# Vérifier que les tables multi-tenant ont été créées
php bin/console doctrine:query:sql "SHOW TABLES LIKE 'cspro_organization%'"
```

**Tables créées** :
- `cspro_organizations` : Liste des organisations
- `cspro_organization_db_connections` : Connexions de bases de données par organisation
- `cspro_organization_dictionaries` : Association dictionnaires → organisations
- `cspro_organization_users` : Association utilisateurs → organisations

### Étape 3 : Créer la Première Organisation

```bash
docker exec -it php bash

# Créer l'organisation ANSD
php bin/console csweb:org:create \
  --code=ANSD \
  --name="Agence Nationale de la Statistique et de la Démographie" \
  --country=SN \
  --type=statistics_office
```

### Étape 4 : Ajouter une Connexion Base de Données

**Option A : Connexion PostgreSQL Externe (BYO Database)**

```bash
# Se connecter au serveur PostgreSQL de l'organisation
php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_RECENSEMENT" \
  --driver=pdo_pgsql \
  --host=192.168.1.50 \
  --port=5432 \
  --db=recensement_2024 \
  --user=csweb_user \
  --password=mot_de_passe_client \
  --default \
  --test
```

**Option B : Connexion PostgreSQL Locale (pour tests)**

```bash
# D'abord, démarrer un conteneur PostgreSQL local
docker run -d \
  --name postgres_test \
  --network csweb_backend \
  -e POSTGRES_USER=cspro \
  -e POSTGRES_PASSWORD=postgres_password \
  -e POSTGRES_DB=ansd_data \
  -p 5432:5432 \
  postgres:15

# Puis ajouter la connexion
php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_TEST" \
  --driver=pdo_pgsql \
  --host=postgres_test \
  --port=5432 \
  --db=ansd_data \
  --user=cspro \
  --password=postgres_password \
  --default \
  --test
```

### Étape 5 : Accéder à l'Interface Multi-Tenant

**URL** : `http://localhost/admin/multi-tenant`

**Authentification** : Utiliser le compte super admin créé lors du setup CSWeb

**Fonctionnalités disponibles** :
- Liste des organisations
- Gestion des connexions de bases de données
- Configuration par organisation
- Logs d'activité

## 📋 Commandes Utiles

### Gestion des Conteneurs

```bash
# Démarrer
cd dev
docker-compose up -d

# Arrêter
docker-compose stop

# Redémarrer
docker-compose restart

# Voir les logs
docker-compose logs -f php

# Entrer dans le conteneur PHP
docker exec -it php bash

# Entrer dans MySQL
docker exec -it mysql mysql -u cspro -pcspro cspro
```

### Gestion des Organisations

```bash
# Lister toutes les organisations
php bin/console doctrine:query:sql \
  "SELECT id, code, name, country FROM cspro_organizations"

# Voir les connexions d'une organisation
php bin/console doctrine:query:sql \
  "SELECT org.code, conn.name, conn.driver, conn.host
   FROM cspro_organizations org
   JOIN cspro_organization_db_connections conn ON org.id = conn.organization_id"

# Tester une connexion
php bin/console csweb:db:test --org=ANSD --connection=POSTGRES_RECENSEMENT
```

### Debugging

```bash
# Vérifier la configuration Symfony
php bin/console debug:config

# Lister les routes disponibles
php bin/console debug:router | grep multi-tenant

# Vérifier les services
php bin/console debug:container | grep Database

# Nettoyer le cache
php bin/console cache:clear
```

## 🚨 Problèmes Connus

### Avertissement phpMyAdmin (linux/amd64 vs linux/arm64)
**Statut** : Non bloquant - L'émulation fonctionne correctement

**Message** :
```
The requested image's platform (linux/amd64) does not match
the detected host platform (linux/arm64/v8)
```

**Solution** : Accepter l'émulation ou utiliser Adminer (léger et multi-plateforme)

### SQL Server sur ARM64
**Statut** : FreeTDS installé mais pdo_odbc pas activé

**Alternatives** :
1. Tester sur architecture x86_64 (drivers natifs Microsoft)
2. Activer pdo_odbc dans le Dockerfile si nécessaire
3. Utiliser PostgreSQL ou MySQL pour les tests initiaux

## 📚 Documentation

- **Guide d'installation complet** : [docs/02-INSTALLATION-GUIDE.md](../docs/02-INSTALLATION-GUIDE.md)
- **Scénarios de déploiement** : [docs/04-DEPLOYMENT-SCENARIOS.md](../docs/04-DEPLOYMENT-SCENARIOS.md)
- **Gestion des organisations** : [docs/03-ORGANIZATION-MANAGEMENT.md](../docs/03-ORGANIZATION-MANAGEMENT.md)
- **Architecture multi-tenant** : [docs/01-MULTI-TENANT-ARCHITECTURE.md](../docs/01-MULTI-TENANT-ARCHITECTURE.md)

## 🎯 Configuration Actuelle

**Mode** : Multi-Tenant BYO Database (Option 2)

**Infrastructure** :
- ✅ CSWeb + MySQL principal (conteneur)
- ✅ Support PostgreSQL (pilote actif)
- ✅ Support MySQL (pilote actif)
- ⚠️ Support SQL Server (FreeTDS installé, non testé)

**Prochaine action** : Ouvrir `http://localhost` dans le navigateur pour compléter le setup CSWeb.
