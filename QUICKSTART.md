# Quick Start - CSWeb Multi-Tenant

## 🚀 Installation en 3 minutes

### Prérequis

- Docker et Docker Compose installés
- 4 Go RAM minimum

### Étapes

#### 1. Configuration de l'environnement

```bash
cd dev

# Copier le fichier .env (ou l'éditer s'il existe déjà)
# Vérifier que CSWEB_ROOT pointe vers ../csweb8
cat .env
```

Le fichier `.env` doit contenir au minimum :

```bash
CSWEB_ROOT=../csweb8
DB_ROOT_PASSWORD=rootpassword
DB_NAME=cspro
DB_USERNAME=cspro
DB_PASSWORD=password
```

#### 2. Démarrer l'environnement

**Option A : Configuration classique CSWeb (SANS multi-tenant)**

```bash
# Utiliser docker-compose standard
docker-compose up -d
```

Puis accéder à **http://localhost** et suivre le setup CSWeb classique.

---

**Option B : Configuration Multi-Tenant (Recommandé)**

```bash
# Utiliser docker-compose multi-tenant
docker-compose -f docker-compose-multitenant.yml up -d
```

#### 3. Configuration initiale CSWeb (navigateur)

Ouvrir **http://localhost** dans votre navigateur.

**Paramètres de configuration** :

| Champ                | Valeur                                |
| -------------------- | ------------------------------------- |
| Database name        | `cspro`                               |
| Hostname             | `mysql`                               |
| Database username    | `cspro`                               |
| Database password    | `password` (ou valeur de DB_PASSWORD) |
| CSWeb admin password | _Choisir un mot de passe sécurisé_    |
| Timezone             | `Africa/Dakar` (ou votre timezone)    |
| Path to files        | `/var/www/html/files`                 |
| CSWeb API URL        | `http://localhost/api`                |

Cliquer sur **Install**.

#### 4. Migration Multi-Tenant (uniquement si Option B)

Une fois CSWeb configuré :

```bash
# Exécuter la migration
docker exec -it csweb_php php bin/console doctrine:migrations:migrate --no-interaction

# Vérifier
docker exec -it csweb_php php bin/console doctrine:schema:validate
```

#### 5. Accès

| Service                | URL                                 | Identifiants                  |
| ---------------------- | ----------------------------------- | ----------------------------- |
| **CSWeb**              | http://localhost                    | admin / (mot de passe choisi) |
| **Admin Multi-Tenant** | http://localhost/admin/multi-tenant | (même que CSWeb)              |
| **phpMyAdmin**         | http://localhost:8080               | root / rootpassword           |
| **pgAdmin**            | http://localhost:8081               | admin@csweb.local / admin     |

---

## 📖 Option A : CSWeb Classique (Simple)

### Avantages

- ✅ Configuration identique à CSWeb standard
- ✅ Pas de migration supplémentaire
- ✅ Fonctionne immédiatement

### Limitations

- ❌ Une seule organisation
- ❌ MySQL uniquement pour breakout
- ❌ Pas d'interface multi-tenant

### Commande

```bash
cd dev
docker-compose up -d
```

**C'est tout !** Accéder à http://localhost

---

## 🌟 Option B : Multi-Tenant (Recommandé)

### Avantages

- ✅ Plusieurs organisations isolées
- ✅ Choix SGBD (PostgreSQL, MySQL, SQL Server)
- ✅ Interface d'administration complète
- ✅ Scalable pour SaaS

### Commande

```bash
cd dev
docker-compose -f docker-compose-multitenant.yml up -d
```

Puis suivre les étapes 3, 4 et 5 ci-dessus.

### Créer votre première organisation

```bash
# Créer l'organisation
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=ANSD \
  --name="ANSD Sénégal" \
  --type=statistics_office \
  --country=SN

# Ajouter une connexion PostgreSQL
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

# Créer la base PostgreSQL
docker exec -it csweb_postgres psql -U cspro -d cspro_data -c "CREATE DATABASE ansd_data;"
```

**OU** utiliser l'interface web : http://localhost/admin/multi-tenant

---

## 🔄 Script Automatique (Option B uniquement)

Pour automatiser toute la configuration Multi-Tenant :

```bash
cd dev
chmod +x setup-multitenant.sh
./setup-multitenant.sh
```

Ce script :

1. ✅ Vérifie Docker
2. ✅ Démarre les conteneurs
3. ✅ Attend que les services soient prêts
4. ✅ Exécute la migration
5. ✅ Propose de créer une organisation exemple

---

## 📊 Services Démarrés

### Option A (docker-compose.yml)

| Service    | Port | Description     |
| ---------- | ---- | --------------- |
| PHP/Apache | 80   | CSWeb           |
| MySQL      | 3306 | Base de données |
| phpMyAdmin | 8080 | Interface MySQL |

### Option B (docker-compose-multitenant.yml)

| Service    | Port | Description            |
| ---------- | ---- | ---------------------- |
| PHP/Apache | 80   | CSWeb                  |
| MySQL      | 3306 | Base principale CSWeb  |
| PostgreSQL | 5432 | Breakout dictionnaires |
| SQL Server | 1433 | Breakout (optionnel)   |
| phpMyAdmin | 8080 | Interface MySQL        |
| pgAdmin    | 8081 | Interface PostgreSQL   |
| Redis      | 6379 | Cache/Sessions         |

---

## 🛠️ Commandes Utiles

### Voir les logs

```bash
# Tous les services
docker-compose -f docker-compose-multitenant.yml logs -f

# Un service spécifique
docker-compose -f docker-compose-multitenant.yml logs -f php
docker-compose -f docker-compose-multitenant.yml logs -f postgres
```

### Arrêter/Démarrer

```bash
# Arrêter
docker-compose -f docker-compose-multitenant.yml stop

# Démarrer
docker-compose -f docker-compose-multitenant.yml start

# Redémarrer
docker-compose -f docker-compose-multitenant.yml restart
```

### Entrer dans un conteneur

```bash
docker exec -it csweb_php bash
docker exec -it csweb_mysql bash
docker exec -it csweb_postgres bash
```

### Réinitialiser complètement

⚠️ **ATTENTION : Supprime toutes les données !**

```bash
docker-compose -f docker-compose-multitenant.yml down -v
docker-compose -f docker-compose-multitenant.yml up -d
```

---

## ❓ Dépannage

### Port déjà utilisé

Si le port 80 est occupé :

```bash
# Modifier docker-compose*.yml
ports:
  - "8000:80"  # Au lieu de "80:80"
```

Puis accéder à http://localhost:8000

### Base de données non accessible

```bash
# Vérifier que MySQL est démarré
docker-compose -f docker-compose-multitenant.yml ps

# Voir les logs MySQL
docker-compose -f docker-compose-multitenant.yml logs mysql
```

### Permissions fichiers

```bash
docker exec -it csweb_php chown -R www-data:www-data /var/www/html/files
docker exec -it csweb_php chmod -R 775 /var/www/html/files
```

---

## 📚 Documentation Complète

Pour aller plus loin, consulter la documentation dans le dossier `docs/` :

- [00-ARCHITECTURE-OVERVIEW.md](docs/00-ARCHITECTURE-OVERVIEW.md) - Architecture générale
- [02-INSTALLATION-GUIDE.md](docs/02-INSTALLATION-GUIDE.md) - Guide détaillé
- [03-ORGANIZATION-MANAGEMENT.md](docs/03-ORGANIZATION-MANAGEMENT.md) - Gestion des organisations

---

## 🎯 Récapitulatif

**Configuration CSWeb classique** :

```bash
cd dev && docker-compose up -d
open http://localhost
```

**Configuration Multi-Tenant** :

```bash
cd dev && docker-compose -f docker-compose-multitenant.yml up -d
open http://localhost
# Puis migration + création organisation
```

**Setup automatique Multi-Tenant** :

```bash
cd dev && ./setup-multitenant.sh
```

C'est tout ! 🎉

---

**Support** : Consulter [docs/README.md](docs/README.md) ou créer une issue sur GitHub.
