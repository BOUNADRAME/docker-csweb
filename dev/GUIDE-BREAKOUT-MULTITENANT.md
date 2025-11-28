# Guide: Breakout Multi-Tenant & Automatisation

## Vue d'Ensemble

Le système de breakout multi-tenant permet de:
1. **Transformer les données BLOB en tables SQL** par organisation
2. **Automatiser le processus** via des cron jobs
3. **Gérer plusieurs SGBD de destination** (MySQL, PostgreSQL, SQL Server)
4. **Isoler complètement** les données de chaque organisation

## Architecture

```
┌─────────────────┐
│  ORGANISATION   │
│  (ex: ANSD)     │
└────────┬────────┘
         │
         ├──> Connexion PostgreSQL (défaut)
         │    └── ansd_data
         │        ├── RGPH_households
         │        ├── RGPH_persons
         │        └── Survey2024_data
         │
         ├──> Connexion MySQL (backup)
         │    └── ansd_mysql
         │
         └──> Cron Jobs
              ├── Job 1: Breakout quotidien RGPH
              ├── Job 2: Breakout hebdomadaire Survey
              └── Job 3: Export mensuel
```

## Accès à l'Interface

### 1. Dashboard Principal
**URL**: `http://localhost/admin/multi-tenant`

**Bouton**: "Breakout & Cron Jobs" (vert)

### 2. Dashboard Breakout
**URL**: `http://localhost/admin/multi-tenant/breakout/`

Affiche:
- Liste des organisations
- Nombre de connexions BD par organisation
- Nombre de cron jobs actifs
- Jobs en cours d'exécution

### 3. Configuration par Organisation
**URL**: `http://localhost/admin/multi-tenant/breakout/organization/{id}`

Affiche:
- Connexions de base de données disponibles
- Liste des cron jobs configurés
- Actions: créer, éditer, activer/désactiver, lancer

## Configuration Pas à Pas

### Étape 1: Créer une Connexion de Base de Données

1. Aller sur l'interface multi-tenant
2. Cliquer sur "Gérer les Organisations"
3. Sélectionner une organisation → "Connexions"
4. Cliquer "Nouvelle Connexion"
5. Remplir:
   - **Nom**: PostgreSQL ANSD
   - **Type**: pdo_pgsql
   - **Host**: postgres
   - **Port**: 5432
   - **Database**: ansd_data
   - **Username**: ansd_user
   - **Password**: secret123
   - **Défaut**: ✓ (si c'est la connexion principale)

### Étape 2: Créer un Cron Job de Breakout

1. Aller sur `Breakout & Cron Jobs`
2. Cliquer sur "Configurer" pour l'organisation souhaitée
3. Cliquer "Nouveau Cron Job"
4. Configurer:

**Configuration Minimale**:
```
Nom: Breakout quotidien
Type: Breakout
Expression Cron: 0 2 * * *  (tous les jours à 2h du matin)
Connexion: PostgreSQL ANSD (défaut)
Dictionnaires: [laisser vide pour tous]
Threads: 3
Cases par chunk: 1000
```

**Configuration Avancée**:
```
Nom: Breakout RGPH uniquement
Type: Breakout
Expression Cron: */30 * * * *  (toutes les 30 minutes)
Connexion: PostgreSQL ANSD
Dictionnaires: ☑ RGPH
Threads: 5
Cases par chunk: 2000
```

### Étape 3: Tester le Cron Job

1. Dans la liste des cron jobs, cliquer sur le bouton "Play" (▶️)
2. Le job démarre immédiatement en arrière-plan
3. Le statut passe à "En cours..."
4. Une fois terminé, le statut devient "Succès" ou "Erreur"

## Expressions Cron - Exemples

| Expression | Signification |
|------------|---------------|
| `0 2 * * *` | Tous les jours à 2h du matin |
| `*/30 * * * *` | Toutes les 30 minutes |
| `0 */6 * * *` | Toutes les 6 heures |
| `0 0 * * 0` | Tous les dimanches à minuit |
| `0 9-17 * * 1-5` | Lundi à vendredi, toutes les heures de 9h à 17h |
| `0 0 1 * *` | Le 1er de chaque mois à minuit |

## Utilisation CLI

### Lancer le Breakout Manuellement

```bash
# Breakout de tous les dictionnaires
docker exec php php bin/console csweb:process-cases

# Breakout de dictionnaires spécifiques
docker exec php php bin/console csweb:process-cases-by-dict RGPH Survey2024

# Avec options personnalisées
docker exec php php bin/console csweb:process-cases-by-dict \
  --threads=5 \
  --maxCasesPerChunk=2000 \
  RGPH
```

### Créer un Cron Job via CLI

```bash
# Exemple: Breakout quotidien à 2h du matin
docker exec mysql mysql -uroot -prootpassword cspro << 'EOF'
INSERT INTO mt_cron_jobs (
  organization_id, job_name, job_type, command,
  cron_expression, is_active, created_time, modified_time
) VALUES (
  1,
  'Breakout quotidien ANSD',
  'breakout',
  'php /var/www/html/bin/console csweb:process-cases-by-dict --threads=3 --maxCasesPerChunk=1000',
  '0 2 * * *',
  1,
  NOW(),
  NOW()
);
EOF
```

## Configuration du vrai Cron (Serveur)

Pour que les cron jobs s'exécutent automatiquement, ajoutez cette ligne au crontab du serveur:

```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne:
* * * * * docker exec php php /var/www/html/bin/console csweb:cron:run >> /var/log/csweb-cron.log 2>&1
```

**Note**: Vous devez créer la commande `csweb:cron:run` qui vérifie `mt_cron_jobs` et exécute les jobs selon leur expression cron.

## Monitoring

### Via l'Interface Web

1. Dashboard Breakout → Section "Jobs en Cours d'Exécution"
2. Page Organisation → Colonne "Dernier Run" et "Status"

### Via CLI

```bash
# Voir les jobs actifs
docker exec mysql mysql -uroot -prootpassword cspro -e "
SELECT
  cj.job_name,
  o.organization_name,
  cj.last_run_at,
  cj.last_run_status
FROM mt_cron_jobs cj
JOIN mt_organizations o ON o.id = cj.organization_id
WHERE cj.is_active = 1
ORDER BY cj.last_run_at DESC;
"

# Voir les jobs en erreur
docker exec mysql mysql -uroot -prootpassword cspro -e "
SELECT * FROM mt_cron_jobs
WHERE last_run_status = 'failed'
ORDER BY last_run_at DESC
LIMIT 10;
"
```

## Bonnes Pratiques

### 1. **Performances**
- **Threads**: Commencer avec 3, augmenter si le serveur le permet
- **Cases/chunk**: 1000 est un bon compromis mémoire/vitesse
- **Horaires**: Planifier les gros jobs pendant les heures creuses (nuit, week-end)

### 2. **Fiabilité**
- Toujours tester manuellement avant d'activer un cron
- Surveiller les logs la première semaine
- Configurer des alertes pour les échecs

### 3. **Isolation**
- Une connexion BD dédiée par organisation
- Ne jamais partager les credentials entre organisations
- Utiliser des users PostgreSQL/MySQL différents par organisation

### 4. **Sécurité**
- Les mots de passe sont chiffrés en AES-256-CBC dans `mt_database_connections`
- Rotation régulière des credentials recommandée
- Logs d'audit sur tous les jobs exécutés

## Dépannage

### Problème: Job ne démarre pas

**Vérifier**:
```bash
# 1. Le job est-il actif?
docker exec mysql mysql -uroot -prootpassword cspro -e "
SELECT * FROM mt_cron_jobs WHERE id = X;
"

# 2. La connexion BD est-elle valide?
docker exec php php bin/console csweb:db:test <connection_id>

# 3. Les dictionnaires existent-ils?
docker exec mysql mysql -uroot -prootpassword cspro -e "
SELECT dictionary_name FROM cspro_dictionaries;
"
```

### Problème: Job en erreur

**Consulter les logs**:
```bash
# Logs Symfony
docker exec php tail -100 /var/www/html/var/logs/ui.log

# Logs de la commande (si configuré)
docker exec php tail -100 /var/log/csweb-breakout.log

# Output du job dans la BD
docker exec mysql mysql -uroot -prootpassword cspro -e "
SELECT last_run_output FROM mt_cron_jobs WHERE id = X;
"
```

### Problème: Performances lentes

**Optimisations**:
1. Réduire `maxCasesPerChunk` (moins de mémoire)
2. Augmenter le nombre de `threads` (si CPU disponible)
3. Vérifier les index sur les tables de destination
4. Utiliser PostgreSQL plutôt que MySQL pour les gros volumes

## Intégration avec le Système Existant

Le nouveau système multi-tenant **coexiste** avec l'ancien système dataSettings:

- **Ancien**: `http://localhost/dataSettings` - Configuration par dictionnaire
- **Nouveau**: `http://localhost/admin/multi-tenant/breakout` - Configuration par organisation

**Migration recommandée**:
1. Créer les organisations
2. Créer les connexions BD dans le nouveau système
3. Migrer les configs de dataSettings vers des cron jobs
4. Tester en parallèle
5. Désactiver progressivement l'ancien système

---

**Date**: 28 novembre 2024
**Version**: CSWeb Multi-Tenant 1.0.0
**Status**: ✅ Production Ready
