# Documentation CSWeb Multi-Tenant

## 📋 Table des Matières

1. [Architecture Globale](00-ARCHITECTURE-OVERVIEW.md)
2. [Architecture Multi-Tenant](01-MULTI-TENANT-ARCHITECTURE.md)
3. [Guide d'Installation](02-INSTALLATION-GUIDE.md)
4. [Gestion des Organisations](03-ORGANIZATION-MANAGEMENT.md)
5. **[Scénarios de Déploiement](04-DEPLOYMENT-SCENARIOS.md)** ⭐ **Nouveau**
6. Configuration des Dictionnaires (À venir)
7. Guide de Sécurité (À venir)

## 🎯 Vue d'Ensemble

CSWeb Multi-Tenant est une extension de CSWeb 8 qui permet de :

✅ **Multi-tenancy complet** : Plusieurs structures/organisations isolées
✅ **Choix du SGBD** : MySQL, PostgreSQL ou SQL Server par organisation
✅ **Breakout flexible** : Configuration libre de la base destinataire par dictionnaire
✅ **Interface d'administration** : Gestion web complète pour le super admin
✅ **Migration facile** : Compatible avec CSWeb 8 existant

## 🚀 Quick Start

### Prérequis

- Docker & Docker Compose
- 4 Go RAM minimum
- 20 Go d'espace disque

### Installation en 5 minutes

```bash
# 1. Cloner le repository
git clone https://github.com/votre-repo/censusFlow.git
cd censusFlow/dev

# 2. Configurer l'environnement
cp .env.example .env
nano .env  # Modifier les mots de passe

# 3. Démarrer les conteneurs
docker-compose -f docker-compose-multitenant.yml up -d

# 4. Configurer CSWeb (navigateur)
open http://localhost

# 5. Exécuter la migration multi-tenant
docker exec -it csweb_php php bin/console doctrine:migrations:migrate

# 6. Créer votre première organisation
docker exec -it csweb_php php bin/console csweb:org:create \
  --code=ANSD \
  --name="ANSD Sénégal" \
  --country=SN
```

### Accès à l'Interface Admin

Une fois installé, accéder à l'interface d'administration :

**URL :** `http://localhost/admin/multi-tenant`

**Compte :** Super admin créé lors du setup CSWeb

## 📊 Architecture

```
┌──────────────────────────────────────────────────┐
│              CSWeb Multi-Tenant                  │
│                                                  │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐│
│  │ Org ANSD   │  │ Org INS    │  │ Org UCAD   ││
│  │            │  │            │  │            ││
│  │ Postgres   │  │ MySQL      │  │ SQLServer  ││
│  └────────────┘  └────────────┘  └────────────┘│
│                                                  │
│  Isolation complète : Chaque org ne voit que    │
│  ses données, dictionnaires et utilisateurs     │
└──────────────────────────────────────────────────┘
```

## 🎓 Concepts Clés

### Organisation (Tenant)

Une **organisation** représente une structure isolée :

- Institut national de statistique
- Université / Centre de recherche
- Cabinet de collecte de données
- ONG, Agence gouvernementale

**Isolation** :

- ✅ Dictionnaires propres
- ✅ Connexions DB propres
- ✅ Utilisateurs propres
- ✅ Données isolées

### Connexion de Base de Données

Chaque organisation peut avoir **plusieurs connexions** :

- Connexion **par défaut** (pour nouveaux dictionnaires)
- Connexions **spécifiques** par projet/enquête
- Support **multi-SGBD** (MySQL, PostgreSQL, SQL Server)

**Exemple :**

```
Organisation ANSD
├── POSTGRES_RECENSEMENT (par défaut)
├── MYSQL_ENQUETES
└── SQLSERVER_DATAWAREHOUSE
```

### Dictionnaire

Comme dans CSWeb classique, mais avec :

- ✅ Appartenance à **une organisation**
- ✅ Choix de la **connexion DB destinataire**
- ✅ **Breakout** vers le SGBD choisi

## 🔐 Sécurité

### Chiffrement des Mots de Passe

Tous les mots de passe de connexion DB sont chiffrés avec **AES-256-CBC**.

**Clé de chiffrement** : Définie dans `.env`

```bash
DATABASE_ENCRYPTION_KEY=$(openssl rand -base64 32)
```

⚠️ **IMPORTANT** : Ne jamais partager cette clé !

### Isolation Row-Level (PostgreSQL)

Pour PostgreSQL, activer le Row-Level Security :

```sql
ALTER TABLE table_name ENABLE ROW LEVEL SECURITY;

CREATE POLICY org_isolation ON table_name
  USING (organization_id = current_setting('app.current_org_id')::int);
```

## 🛠️ Commandes Utiles

### Gestion des Organisations

```bash
# Créer organisation
php bin/console csweb:org:create --code=ANSD --name="ANSD Sénégal"

# Lister organisations
php bin/console doctrine:query:sql "SELECT * FROM cspro_organizations"
```

### Gestion des Connexions

```bash
# Ajouter connexion PostgreSQL
php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_PROD" \
  --driver=pdo_pgsql \
  --host=postgres \
  --db=ansd_data \
  --user=cspro \
  --password=secret \
  --default \
  --test

# Lister connexions
php bin/console doctrine:query:sql \
  "SELECT * FROM cspro_organization_db_connections"
```

### Docker

```bash
# Démarrer
docker-compose -f docker-compose-multitenant.yml up -d

# Arrêter
docker-compose -f docker-compose-multitenant.yml stop

# Logs
docker-compose -f docker-compose-multitenant.yml logs -f php

# Entrer dans conteneur
docker exec -it csweb_php bash
```

## 📚 Documentation Complète

| Document                                                           | Description                                |
| ------------------------------------------------------------------ | ------------------------------------------ |
| [00-ARCHITECTURE-OVERVIEW.md](00-ARCHITECTURE-OVERVIEW.md)         | Architecture globale et flux de données    |
| [01-MULTI-TENANT-ARCHITECTURE.md](01-MULTI-TENANT-ARCHITECTURE.md) | Architecture technique détaillée           |
| [02-INSTALLATION-GUIDE.md](02-INSTALLATION-GUIDE.md)               | Guide d'installation pas à pas             |
| [03-ORGANIZATION-MANAGEMENT.md](03-ORGANIZATION-MANAGEMENT.md)     | Gestion des organisations et connexions    |
| **[04-DEPLOYMENT-SCENARIOS.md](04-DEPLOYMENT-SCENARIOS.md)**       | **Scénarios de déploiement (SaaS vs BYO)** |

## 🆘 Support

### Problèmes Courants

**Connexion DB échoue**

```bash
# Tester manuellement
docker exec -it csweb_postgres psql -U cspro -d cspro_data
```

**Permissions fichiers**

```bash
docker exec -it csweb_php chown -R www-data:www-data /var/www/html/files
```

**Réinitialiser**

```bash
docker-compose -f docker-compose-multitenant.yml down -v
docker-compose -f docker-compose-multitenant.yml up -d
```

### Obtenir de l'Aide

- 📖 Lire la documentation complète
- 🐛 Créer une issue sur GitHub
- 💬 Contacter le support

## 🤝 Contribution

Les contributions sont les bienvenues !

1. Fork le projet
2. Créer une branche (`git checkout -b feature/AmazingFeature`)
3. Commit (`git commit -m 'Add AmazingFeature'`)
4. Push (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir [LICENSE](../LICENSE) pour plus de détails.

## 🙏 Remerciements

- **CSPro Team** (US Census Bureau) pour CSWeb original
- **Communauté CSPro** pour les retours et suggestions
- **ANSD Sénégal** pour le cas d'usage et les tests en production

---

**Développé avec ❤️ pour la communauté statistique africaine**

**Version :** 1.0.0-beta
**Dernière mise à jour :** Novembre 2024
