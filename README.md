# CSWeb Multi-Tenant Pro

**Modernisation de CSWeb avec Configuration Flexible et Support Multi-SGBD**

[![Docker](https://img.shields.io/badge/docker-ready-blue.svg)](https://www.docker.com)
[![PHP](https://img.shields.io/badge/php-8.2-purple.svg)](https://www.php.net)
[![PostgreSQL](https://img.shields.io/badge/postgresql-15-blue.svg)](https://www.postgresql.org)
[![MySQL](https://img.shields.io/badge/mysql-8.0-orange.svg)](https://www.mysql.com)

Extension de [CSWeb 8.0](https://www.csprousers.org/help/CSWeb/) avec support multi-tenant et choix libre du SGBD destinataire pour le breakout des dictionnaires.

---

## 🌟 Nouveautés

### ✅ Multi-Tenancy Complet
- Plusieurs organisations/structures isolées sur une même instance
- Chaque organisation ne voit que ses données, dictionnaires et utilisateurs
- Isolation complète au niveau base de données

### ✅ Choix du SGBD Destinataire
- **PostgreSQL** (recommandé pour gros volumes - recensements)
- **MySQL** (compatibilité et petites enquêtes)
- **SQL Server** (environnements Microsoft/gouvernements)

### ✅ Interface d'Administration Web
- Dashboard web pour le super admin
- Gestion des organisations via interface
- Configuration des connexions multi-SGBD
- Test de connexion intégré

### ✅ Architecture Sécurisée
- MySQL principal (imposé par CSPro) pour métadonnées et BLOBs
- Breakout vers le SGBD de votre choix
- Connection pooling pour performances
- Chiffrement des credentials (AES-256-CBC)

---

## 📋 Trois Configurations Disponibles

### 1. **CSWeb Classique** (Simple)
Configuration standard CSWeb 8.0 avec MySQL uniquement - **Une seule organisation**.

**Commande** : `docker-compose up -d`

**Utilisation** : Setup CSWeb classique

### 2. **Multi-Tenant BYO Database** (Production - Recommandé)
Multi-organisations avec MySQL principal uniquement. **Chaque organisation utilise ses propres serveurs** de bases de données pour le breakout.

**Commande** : `docker-compose up -d` + Configuration des connexions externes

**Cas d'usage** : Instituts nationaux (ANSD, INS) avec infrastructure existante

### 3. **Multi-Tenant SaaS Hébergé** (SaaS Provider)
Multi-organisations avec **tous les SGBD hébergés** par vous. Clients n'ont aucune infrastructure à gérer.

**Commande** : `docker-compose -f docker-compose-multitenant.yml up -d`

**Cas d'usage** : Vous hébergez tout pour plusieurs clients (ONG, universités)

---

## 🚀 Quick Start

### Prérequis

- Docker et Docker Compose ([installer](https://docs.docker.com/install/))
- 4 Go RAM minimum (8 Go recommandé pour multi-tenant)
- 20 Go espace disque

### Installation CSWeb Classique (Option Simple)

```bash
# 1. Aller dans le dossier dev
cd dev

# 2. Lancer le script de setup automatique
./setup-csweb.sh

# OU manuellement :
# docker-compose up -d
# docker exec php bash -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
# docker exec php composer install --working-dir=/var/www/html --no-interaction --optimize-autoloader

# 3. Configurer via navigateur
open http://localhost
```

Suivre les instructions de configuration CSWeb standard.

### Installation Multi-Tenant BYO Database (Production)

```bash
# 1. Aller dans le dossier dev
cd dev

# 2. Lancer le script de setup automatique
./setup-csweb.sh

# 3. Configurer CSWeb via navigateur
open http://localhost

# 4. Exécuter la migration multi-tenant
docker exec php php bin/console doctrine:migrations:migrate --no-interaction

# 5. Créer une organisation
docker exec php php bin/console csweb:org:create \
  --code=ANSD --name="ANSD Sénégal"

# 6. Connecter au serveur PostgreSQL de l'organisation (EXTERNE)
docker exec php php bin/console csweb:db:add \
  --org=ANSD \
  --driver=pdo_pgsql \
  --host=192.168.1.50 \
  --db=recensement_2024 \
  --user=csweb_user \
  --password=leur_mot_de_passe \
  --default \
  --test

# 7. Accéder à l'interface admin
open http://localhost/admin/multi-tenant
```

### Installation Multi-Tenant SaaS (Tous SGBD Hébergés)

```bash
# 1. Aller dans le dossier dev
cd dev

# 2. Démarrer environnement complet (MySQL + PostgreSQL + SQL Server)
docker-compose -f docker-compose-multitenant.yml up -d

# 2b. Installer Composer et dépendances
docker exec php bash -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
docker exec php composer install --working-dir=/var/www/html --no-interaction --optimize-autoloader

# 3-7. Mêmes étapes que ci-dessus
# Mais pour l'étape 6, utiliser vos serveurs internes :
docker exec php php bin/console csweb:db:add \
  --org=ANSD \
  --driver=pdo_pgsql \
  --host=postgres \
  --db=ansd_data \
  --user=cspro \
  --password=postgres_password \
  --default \
  --test
```

**Script automatique (SaaS uniquement)** :

```bash
cd dev
./setup-multitenant.sh
```

**Guide détaillé** : [QUICKSTART.md](QUICKSTART.md) | **Scénarios** : [docs/04-DEPLOYMENT-SCENARIOS.md](docs/04-DEPLOYMENT-SCENARIOS.md)

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| **[QUICKSTART.md](QUICKSTART.md)** | 🚀 Installation rapide (3 minutes) |
| **[docs/04-DEPLOYMENT-SCENARIOS.md](docs/04-DEPLOYMENT-SCENARIOS.md)** | 🎯 Scénarios de déploiement (SaaS vs BYO Database) |
| [docs/00-ARCHITECTURE-OVERVIEW.md](docs/00-ARCHITECTURE-OVERVIEW.md) | 🏗️ Architecture générale et flux de données |
| [docs/01-MULTI-TENANT-ARCHITECTURE.md](docs/01-MULTI-TENANT-ARCHITECTURE.md) | 🔧 Architecture technique détaillée |
| [docs/02-INSTALLATION-GUIDE.md](docs/02-INSTALLATION-GUIDE.md) | 📖 Guide d'installation complet |
| [docs/03-ORGANIZATION-MANAGEMENT.md](docs/03-ORGANIZATION-MANAGEMENT.md) | 👥 Gestion des organisations |
| [docs/IMPLEMENTATION-STATUS.md](docs/IMPLEMENTATION-STATUS.md) | ✅ État de l'implémentation |

---

## 🎯 Cas d'Usage

### Scénario 1 : Institut National de Statistique

**Organisation** : ANSD Sénégal

```bash
# Créer l'organisation
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=ANSD \
  --name="ANSD Sénégal" \
  --type=statistics_office

# Ajouter connexion PostgreSQL pour recensement (gros volumes)
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_RECENSEMENT" \
  --driver=pdo_pgsql \
  --host=postgres \
  --db=ansd_recensement \
  --user=ansd \
  --password=secret \
  --default

# Ajouter connexion MySQL pour enquêtes (petits volumes)
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=ANSD \
  --name="MYSQL_ENQUETES" \
  --driver=pdo_mysql \
  --host=mysql \
  --db=ansd_enquetes \
  --user=ansd \
  --password=secret
```

### Scénario 2 : Hébergeur SaaS Multi-Pays

```bash
# Organisation ANSD Sénégal
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=ANSD --name="ANSD Sénégal" --country=SN

# Organisation INS Bénin
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=INS_BENIN --name="INS Bénin" --country=BJ

# Organisation INS Côte d'Ivoire
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=INS_CI --name="INS Côte d'Ivoire" --country=CI
```

Chaque organisation a ses propres connexions DB et **isolation totale**.

---

## 🔐 Sécurité

### Isolation Multi-Tenant

- ✅ Séparation complète des données par organisation
- ✅ Row-Level Security (PostgreSQL)
- ✅ Chiffrement AES-256-CBC des credentials
- ✅ Logs d'audit complets

### Génération Clé de Chiffrement

```bash
# Générer une clé sécurisée
openssl rand -base64 32

# Ajouter dans dev/.env
DATABASE_ENCRYPTION_KEY=<clé générée>
```

---

## 🛠️ Commandes Utiles

### Docker

```bash
# Démarrer (multi-tenant)
docker-compose -f docker-compose-multitenant.yml up -d

# Arrêter
docker-compose -f docker-compose-multitenant.yml stop

# Voir les logs
docker-compose -f docker-compose-multitenant.yml logs -f

# Entrer dans conteneur PHP
docker exec -it csweb_php bash
```

### Gestion Organisations

```bash
# Créer organisation
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=ORG --name="Nom Organisation"

# Lister organisations
docker exec -it csweb_php php bin/console doctrine:query:sql \
  "SELECT * FROM cspro_organizations"
```

### Gestion Connexions

```bash
# Ajouter connexion
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=ORG --name=CONN --driver=pdo_pgsql \
  --host=postgres --db=data --user=user --password=pass --test

# Lister connexions
docker exec -it csweb_php php bin/console doctrine:query:sql \
  "SELECT * FROM cspro_organization_db_connections"
```

---

## 🌐 Accès aux Services

| Service | URL | Identifiants (défaut) |
|---------|-----|----------------------|
| **CSWeb** | http://localhost | admin / (configuré au setup) |
| **Admin Multi-Tenant** | http://localhost/admin/multi-tenant | (même que CSWeb) |
| **phpMyAdmin** | http://localhost:8080 | root / rootpassword |
| **pgAdmin** | http://localhost:8081 | admin@csweb.local / admin |

---

## 🏗️ Architecture

### Flux de Données

```
Mobile CSEntry
    ↓ Synchronisation
API CSWeb (:80/api)
    ↓ Stockage BLOB
MySQL Principal (cspro)
    ↓ Breakout
PostgreSQL / MySQL / SQL Server
    ↓ Données Tabulaires
Exploitation (SPSS, Stata, R, Python, BI Tools)
```

### Isolation par Organisation

```
┌─────────────────────────────────────────┐
│   Organisation ANSD (Sénégal)          │
│   ├── Dictionnaires: RECENSEMENT_2024  │
│   ├── Connexion: PostgreSQL            │
│   └── Utilisateurs: admin@ansd.sn      │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│   Organisation INS (Bénin)             │
│   ├── Dictionnaires: RGPH_2024         │
│   ├── Connexion: MySQL                 │
│   └── Utilisateurs: admin@insae.bj     │
└─────────────────────────────────────────┘
```

**Isolation complète** : Aucune fuite de données entre organisations !

---

## 🤝 Contribution

Les contributions sont bienvenues !

1. Fork le projet
2. Créer une branche (`git checkout -b feature/AmazingFeature`)
3. Commit (`git commit -m 'Add AmazingFeature'`)
4. Push (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

---

## 📄 Licence

Ce projet est sous licence MIT. Voir [LICENSE](LICENSE) pour plus de détails.

---

## 🙏 Remerciements

- **CSPro Team** (US Census Bureau) pour CSWeb original
- **Communauté CSPro** pour les retours et suggestions
- **ANSD Sénégal** pour le cas d'usage et les tests en production

---

**Développé avec ❤️ pour la communauté statistique africaine** 🌍

**Version :** 1.0.0-beta
**Dernière mise à jour :** Novembre 2024
