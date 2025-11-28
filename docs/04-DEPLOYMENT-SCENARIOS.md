# Scénarios de Déploiement - CSWeb Multi-Tenant

## Vue d'Ensemble

CSWeb Multi-Tenant supporte **deux modes de déploiement** selon votre modèle d'hébergement :

1. **Mode SaaS Hébergé** : Vous hébergez tous les SGBD
2. **Mode BYO Database** : Les structures utilisent leurs propres serveurs de bases de données

## 🏢 Scénario 1 : Mode SaaS Hébergé

### Description

Vous hébergez **tout** : CSWeb + MySQL principal + Tous les SGBD destinataires.

Les structures n'ont **aucune infrastructure** à gérer - elles utilisent votre plateforme comme service.

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  Votre Infrastructure SaaS                   │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ MySQL Principal (imposé CSPro)                      │   │
│  │ - Métadonnées de toutes les organisations          │   │
│  │ - BLOBs de tous les questionnaires                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ PostgreSQL (mutualisé)                              │   │
│  │ ├── ansd_recensement (Organisation ANSD)           │   │
│  │ ├── ins_benin_rgph (Organisation INS Bénin)        │   │
│  │ └── ins_ci_enquete (Organisation INS CI)           │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ MySQL Secondaire (mutualisé)                        │   │
│  │ ├── org1_data (Petites enquêtes)                   │   │
│  │ └── org2_data                                       │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SQL Server (mutualisé)                              │   │
│  │ └── gov_agency_data (Organisations gouvernementales)│   │
│  └─────────────────────────────────────────────────────┘   │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### Avantages

- ✅ **Simplicité pour les clients** : Aucune infrastructure à gérer
- ✅ **Contrôle total** : Vous gérez sauvegardes, performances, sécurité
- ✅ **Modèle SaaS** : Facturation possible par organisation/volume
- ✅ **Support unifié** : Une seule infrastructure à maintenir
- ✅ **Déploiement rapide** : Nouvelles organisations en quelques minutes

### Inconvénients

- ❌ **Coûts infrastructure** : Vous supportez tous les coûts serveurs
- ❌ **Souveraineté des données** : Les données ne sont pas dans le pays du client
- ❌ **Scalabilité** : Vous devez provisionner pour la croissance
- ❌ **Dépendance** : Les clients dépendent de votre disponibilité

### Configuration Docker

**Fichier** : `docker-compose-multitenant.yml`

```yaml
services:
  php:
    # CSWeb

  mysql:
    # Base principale (imposée CSPro)
    # + Métadonnées multi-tenant

  postgres:
    # PostgreSQL pour breakout
    # Bases séparées par organisation

  sqlserver:
    # SQL Server pour breakout
    # Organisations Microsoft/gouvernementales

  phpmyadmin:
    # Interface MySQL

  pgadmin:
    # Interface PostgreSQL

  redis:
    # Cache/Sessions
```

### Commandes

```bash
# Démarrer tout
cd dev
docker-compose -f docker-compose-multitenant.yml up -d

# Créer une organisation
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=ANSD --name="ANSD Sénégal"

# Ajouter connexion sur votre PostgreSQL mutualisé
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_PROD" \
  --driver=pdo_pgsql \
  --host=postgres \
  --port=5432 \
  --db=ansd_recensement \
  --user=cspro \
  --password=postgres_password \
  --default \
  --test
```

### Cas d'Usage

- 🎯 **Hébergeur SaaS** multi-pays (vous hébergez pour plusieurs INS)
- 🎯 **Organisation unique** avec plusieurs projets (université, ONG)
- 🎯 **Prototypage rapide** sans infrastructure existante

---

## 🔌 Scénario 2 : Mode BYO Database (Bring Your Own)

### Description

Vous hébergez **uniquement CSWeb + MySQL principal**.

Chaque structure utilise **ses propres serveurs** de bases de données pour le breakout.

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│              Votre Infrastructure (Légère)                   │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────┐   │
│  │ MySQL Principal (imposé CSPro)                      │   │
│  │ - Métadonnées de toutes les organisations          │   │
│  │ - BLOBs de tous les questionnaires                 │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                    Connexions réseau
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌───────────────┐   ┌───────────────┐   ┌───────────────┐
│ ANSD Sénégal  │   │ INS Bénin     │   │ INS Côte d'Iv.│
├───────────────┤   ├───────────────┤   ├───────────────┤
│ PostgreSQL    │   │ SQL Server    │   │ MySQL         │
│ 192.168.1.50  │   │ 10.0.0.100    │   │ db.ins-ci.net │
│ Port: 5432    │   │ Port: 1433    │   │ Port: 3306    │
│               │   │               │   │               │
│ LEUR datacenter│   │ LEUR serveur  │   │ LEUR cloud    │
└───────────────┘   └───────────────┘   └───────────────┘
```

### Avantages

- ✅ **Souveraineté des données** : Données restent dans le pays/datacenter du client
- ✅ **Contrôle client** : Chaque structure gère ses sauvegardes/performances
- ✅ **Infrastructure légère** : Vous n'hébergez que MySQL + CSWeb
- ✅ **Conformité** : Respect des réglementations locales sur les données
- ✅ **Utilisation de l'existant** : Réutilisation de serveurs déjà en place

### Inconvénients

- ❌ **Complexité réseau** : Gérer les connexions réseau sortantes
- ❌ **Support distribué** : Problèmes de performance = plusieurs responsables
- ❌ **Configuration client** : Chaque client doit provisionner son infrastructure
- ❌ **Sécurité réseau** : Firewall, VPN, whitelisting IPs

### Configuration Docker

**Fichier** : `docker-compose.yml` (Standard - MySQL uniquement)

```yaml
services:
  php:
    # CSWeb

  mysql:
    # Base principale (imposée CSPro)
    # + Métadonnées multi-tenant

  phpmyadmin:
    # Interface MySQL
```

**Beaucoup plus léger** - Pas de PostgreSQL, SQL Server, Redis dans vos conteneurs.

### Commandes

```bash
# Démarrer (configuration simple)
cd dev
docker-compose up -d

# Créer une organisation
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=ANSD --name="ANSD Sénégal"

# Ajouter connexion vers LEUR PostgreSQL
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_ANSD_EXTERNE" \
  --driver=pdo_pgsql \
  --host=192.168.1.50 \
  --port=5432 \
  --db=recensement_2024 \
  --user=csweb_user \
  --password=leur_mot_de_passe \
  --default \
  --test

# Connexion vers SQL Server d'INS Bénin
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=INS_BENIN \
  --name="SQLSERVER_INS_BENIN" \
  --driver=sqlsrv \
  --host=10.0.0.100 \
  --port=1433 \
  --db=rgph_2024 \
  --user=csweb_app \
  --password=leur_mot_de_passe \
  --default \
  --test
```

### Prérequis Côté Client

Chaque structure doit :

1. **Provisionner un serveur de base de données** (PostgreSQL/MySQL/SQL Server)
2. **Créer une base de données** dédiée pour CSWeb
3. **Créer un utilisateur** avec droits CREATE TABLE, INSERT, UPDATE, DELETE
4. **Ouvrir le firewall** pour autoriser votre serveur CSWeb (whitelist IP)
5. **Fournir les credentials** : host, port, database, user, password

### Cas d'Usage

- 🎯 **Instituts nationaux** avec infrastructure existante (ANSD, INS, etc.)
- 🎯 **Contraintes réglementaires** : données doivent rester dans le pays
- 🎯 **Organisations avec DBA** : équipes techniques existantes
- 🎯 **Environnements sécurisés** : Gouvernements, datacenter on-premise

---

## 📊 Tableau Comparatif

| Critère | SaaS Hébergé | BYO Database |
|---------|-------------|--------------|
| **Configuration Docker** | `docker-compose-multitenant.yml` | `docker-compose.yml` |
| **Services hébergés par vous** | MySQL + PostgreSQL + SQL Server + Redis | MySQL uniquement |
| **Infrastructure client** | Aucune | Serveur de base de données |
| **Souveraineté données** | Vos serveurs | Serveurs du client |
| **Complexité setup** | ⭐⭐ Moyenne | ⭐⭐⭐ Élevée (réseau) |
| **Coûts infrastructure** | 💰💰💰 Élevés (vous) | 💰 Faibles (vous) |
| **Support** | ✅ Centralisé | ⚠️ Distribué |
| **Time to market** | 🚀 Rapide | 🐌 Dépend du client |
| **Conformité locale** | ❌ Données hors pays | ✅ Données locales |

---

## 🎯 Recommandation par Cas d'Usage

### Utilisez **SaaS Hébergé** (`docker-compose-multitenant.yml`) si :

- ✅ Vous proposez un **service SaaS payant**
- ✅ Vos clients n'ont **pas d'infrastructure**
- ✅ Vous voulez un **time to market rapide**
- ✅ Les **coûts serveurs** ne sont pas un problème
- ✅ La **souveraineté des données** n'est pas critique

**Exemple** : Startup proposant CSWeb-as-a-Service pour ONG/universités africaines

### Utilisez **BYO Database** (`docker-compose.yml`) si :

- ✅ Vos clients sont des **instituts nationaux avec infrastructure**
- ✅ **Conformité locale** requise (données doivent rester dans le pays)
- ✅ Clients ont déjà des **serveurs de base de données**
- ✅ Vous voulez une **infrastructure légère**
- ✅ Support technique **distribué** acceptable

**Exemple** : Solution pour ministères/INS d'Afrique de l'Ouest (ANSD, INS Bénin, INS CI, etc.)

---

## 🔀 Mode Hybride (Recommandé)

**Combinez les deux approches** selon le profil client :

```bash
# Configuration de base (MySQL uniquement)
docker-compose up -d

# Client avec infrastructure → BYO Database
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=ANSD \
  --host=192.168.1.50  # Leur serveur PostgreSQL

# Client sans infrastructure → Votre PostgreSQL mutualisé
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=SMALL_NGO \
  --host=postgres_saas.your-domain.com  # Votre serveur
```

**Avantage** :
- Flexibilité maximale
- Démarrez avec `docker-compose.yml` (léger)
- Ajoutez des SGBD mutualisés au besoin avec `docker-compose-multitenant.yml`

---

## 🛠️ Migration Entre Scénarios

### De Simple vers Multi-Tenant SaaS

```bash
# 1. Arrêter configuration simple
docker-compose down

# 2. Démarrer configuration multi-tenant
docker-compose -f docker-compose-multitenant.yml up -d

# 3. Migrer les connexions existantes
# Les connexions externes continuent de fonctionner
# Ajoutez nouvelles connexions vers vos SGBD mutualisés
```

### De Multi-Tenant vers Externe

```bash
# 1. Client provisionne son serveur PostgreSQL
# 2. Vous créez nouvelle connexion vers leur serveur
docker exec -it csweb_php php bin/console csweb:db:add \
  --org=CLIENT \
  --host=leur.serveur.com

# 3. Migrer les données
pg_dump -h postgres | psql -h leur.serveur.com

# 4. Définir nouvelle connexion par défaut
# 5. Supprimer ancienne connexion mutualisée
```

---

## 📚 Voir Aussi

- [00-ARCHITECTURE-OVERVIEW.md](00-ARCHITECTURE-OVERVIEW.md) - Architecture globale
- [02-INSTALLATION-GUIDE.md](02-INSTALLATION-GUIDE.md) - Installation détaillée
- [03-ORGANIZATION-MANAGEMENT.md](03-ORGANIZATION-MANAGEMENT.md) - Gestion des organisations

---

**Conclusion** : Le choix entre SaaS hébergé et BYO Database dépend de votre modèle d'affaires et du profil de vos clients. Les deux sont pleinement supportés par CSWeb Multi-Tenant.
