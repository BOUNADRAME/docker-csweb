# Guide d'Installation - CSWeb Multi-Tenant

## Vue d'ensemble

Ce guide vous accompagne dans l'installation et la configuration de CSWeb Multi-Tenant avec support de plusieurs SGBD (MySQL, PostgreSQL, SQL Server).

## Prérequis

- Docker & Docker Compose
- Git
- 4 Go RAM minimum (8 Go recommandé)
- 20 Go d'espace disque

## Architecture Déployée

```
┌─────────────────────────────────────────────┐
│           CSWeb Multi-Tenant                │
├─────────────────────────────────────────────┤
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │  MySQL   │  │PostgreSQL│  │SQLServer │ │
│  │  (Meta)  │  │ (Data)   │  │ (Data)   │ │
│  └──────────┘  └──────────┘  └──────────┘ │
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │phpMyAdmin│  │ pgAdmin  │  │  Redis   │ │
│  └──────────┘  └──────────┘  └──────────┘ │
└─────────────────────────────────────────────┘
```

## Installation Pas à Pas

### 1. Cloner le Repository

```bash
git clone https://github.com/votre-repo/censusFlow.git
cd censusFlow
```

### 2. Configuration de l'Environnement

Copier et éditer le fichier `.env` :

```bash
cd dev
cp .env.example .env
nano .env
```

**Fichier `.env` :**

```bash
# Versions
PHP_VERSION=8.2.6
MYSQL_VERSION=8.0.33

# MySQL - Base de données principale (métadonnées CSWeb)
DB_ROOT_PASSWORD=VotreMotDePasseSecurisé
DB_NAME=cspro
DB_USERNAME=cspro
DB_PASSWORD=VotreMotDePasseSecurisé

# PostgreSQL - Pour breakout dictionnaires
PG_USERNAME=cspro
PG_PASSWORD=VotreMotDePassePostgres
PG_DATABASE=cspro_data

# SQL Server (optionnel)
MSSQL_SA_PASSWORD=VotreMotDePasseSQL!2024

# pgAdmin
PGADMIN_EMAIL=admin@csweb.local
PGADMIN_PASSWORD=admin

# CSWeb Source
CSWEB_ROOT=../csweb8

# Clé de chiffrement (IMPORTANT: Générer une clé sécurisée)
DATABASE_ENCRYPTION_KEY=$(openssl rand -base64 32)
```

⚠️ **IMPORTANT** : Remplacez tous les mots de passe par des valeurs sécurisées en production !

### 3. Démarrer les Conteneurs Docker

```bash
# Utiliser le docker-compose multi-tenant
docker-compose -f docker-compose-multitenant.yml up -d
```

Vérifier que tous les conteneurs sont démarrés :

```bash
docker-compose -f docker-compose-multitenant.yml ps
```

Vous devriez voir :

```
NAME                    STATUS       PORTS
csweb_php               Up           0.0.0.0:80->80/tcp
csweb_mysql             Up           0.0.0.0:3306->3306/tcp
csweb_postgres          Up (healthy) 0.0.0.0:5432->5432/tcp
csweb_sqlserver         Up (healthy) 0.0.0.0:1433->1433/tcp
csweb_phpmyadmin        Up           0.0.0.0:8080->80/tcp
csweb_pgadmin           Up           0.0.0.0:8081->80/tcp
csweb_redis             Up           0.0.0.0:6379->6379/tcp
```

### 4. Configuration Initiale de CSWeb

Accéder à l'interface web : `http://localhost`

Suivre les étapes de configuration :

| Paramètre               | Valeur                          |
|-------------------------|---------------------------------|
| Database name           | cspro                           |
| Hostname                | mysql                           |
| Database username       | cspro                           |
| Database password       | (valeur de DB_PASSWORD)         |
| CSWeb admin password    | Choisir un mot de passe sécurisé|
| Timezone                | Africa/Dakar (ou votre timezone)|
| Path to files           | /var/www/html/files             |
| CSWeb API URL           | http://localhost/api            |

### 5. Exécuter la Migration Multi-Tenant

Une fois CSWeb configuré, exécuter la migration :

```bash
# Entrer dans le conteneur PHP
docker exec -it csweb_php bash

# Exécuter la migration
cd /var/www/html
php bin/console doctrine:migrations:migrate --no-interaction

# Vérifier que les tables sont créées
php bin/console doctrine:schema:validate
```

### 6. Créer votre Première Organisation

```bash
# Toujours dans le conteneur
php bin/console csweb:org:create \
  --code=ANSD \
  --name="ANSD Sénégal" \
  --type=statistics_office \
  --country=SN \
  --email=contact@ansd.sn
```

### 7. Ajouter une Connexion PostgreSQL pour l'Organisation

```bash
php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_PROD" \
  --driver=pdo_pgsql \
  --host=postgres \
  --port=5432 \
  --db=ansd_data \
  --user=cspro \
  --password=VotreMotDePassePostgres \
  --default \
  --test
```

Si le test de connexion réussit, vous verrez :

```
✓ Database connection created successfully!
✓ Connection successful
```

### 8. Créer une Base de Données PostgreSQL pour l'Organisation

```bash
# Se connecter à PostgreSQL
docker exec -it csweb_postgres psql -U cspro -d cspro_data

# Créer le schéma pour ANSD
CREATE DATABASE ansd_data;
GRANT ALL PRIVILEGES ON DATABASE ansd_data TO cspro;

# Quitter
\q
```

## Accès aux Interfaces

| Service     | URL                    | Credentials                          |
|-------------|------------------------|--------------------------------------|
| CSWeb       | http://localhost       | admin / (mot de passe configuré)     |
| phpMyAdmin  | http://localhost:8080  | root / (DB_ROOT_PASSWORD)            |
| pgAdmin     | http://localhost:8081  | (PGADMIN_EMAIL / PGADMIN_PASSWORD)   |

## Configuration pgAdmin (Premier Accès)

1. Accéder à `http://localhost:8081`
2. Se connecter avec les credentials du `.env`
3. Ajouter un serveur PostgreSQL :
   - **Name:** CSWeb Postgres
   - **Host:** postgres
   - **Port:** 5432
   - **Username:** cspro
   - **Password:** (valeur de PG_PASSWORD)

## Vérification de l'Installation

### 1. Vérifier les Tables

```bash
docker exec -it csweb_php php bin/console doctrine:schema:validate
```

Doit afficher :

```
✓ The mapping files are correct.
✓ The database schema is in sync with the mapping files.
```

### 2. Lister les Organisations

```bash
docker exec -it csweb_php php bin/console doctrine:query:sql \
  "SELECT * FROM cspro_organizations"
```

### 3. Lister les Connexions

```bash
docker exec -it csweb_php php bin/console doctrine:query:sql \
  "SELECT id, organization_id, connection_name, db_driver, db_host, db_name
   FROM cspro_organization_db_connections"
```

## Dépannage

### Conteneur ne démarre pas

```bash
# Voir les logs
docker-compose -f docker-compose-multitenant.yml logs <service_name>

# Exemples:
docker-compose -f docker-compose-multitenant.yml logs postgres
docker-compose -f docker-compose-multitenant.yml logs php
```

### Problème de Permissions

```bash
# Fix permissions CSWeb
docker exec -it csweb_php chown -R www-data:www-data /var/www/html/files
docker exec -it csweb_php chmod -R 775 /var/www/html/files
```

### Réinitialiser Complètement

⚠️ **ATTENTION : Supprime toutes les données !**

```bash
docker-compose -f docker-compose-multitenant.yml down -v
docker-compose -f docker-compose-multitenant.yml up -d
```

## Commandes Utiles

```bash
# Arrêter les conteneurs
docker-compose -f docker-compose-multitenant.yml stop

# Démarrer les conteneurs
docker-compose -f docker-compose-multitenant.yml start

# Redémarrer un service
docker-compose -f docker-compose-multitenant.yml restart php

# Voir les logs en temps réel
docker-compose -f docker-compose-multitenant.yml logs -f

# Entrer dans un conteneur
docker exec -it csweb_php bash
docker exec -it csweb_mysql bash
docker exec -it csweb_postgres bash
```

## Prochaines Étapes

Consultez les guides suivants :

- [Guide de Gestion des Organisations](03-ORGANIZATION-MANAGEMENT.md)
- [Guide de Configuration des Dictionnaires](04-DICTIONARY-SETUP.md)
- [Guide de Sécurité](05-SECURITY-GUIDE.md)

---

**Support** : Pour toute question, consulter la [documentation complète](README.md) ou créer une issue sur GitHub.
