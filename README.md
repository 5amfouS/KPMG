# KPMG
# Plateforme de Gestion des Entreprises et Employés

## Objectif

Ce projet est une plateforme web destinée aux services RH (ressources humaines) pour gérer :
- les entreprises,
- leurs employés,
- et l’envoi sécurisé des fiches de paie.

Deux types d’utilisateurs peuvent accéder au système :  
- Les **RH**, qui gèrent les entreprises et les employés.  
- Les **Admins**, qui gèrent les comptes RH et la conformité du site.

---

## Utilisateurs & Rôles

| Rôle      | Fonctionnalités |
|-----------|-----------------|
| **RH**    | - Créer, modifier, supprimer des entreprises  
|           | - Gérer les employés  
|           | - Envoyer des fiches de paie sécurisées (PDF protégé par mot de passe, envoyé par SMS) |
| **Admin** | - Gérer les utilisateurs RH  
|           | - Envoyer des fiches de paie sécurisées (PDF protégé par mot de passe, envoyé par SMS) |
|           | - Surveiller la conformité du système |

---

##  Sécurité des fiches de paie

- Les fiches de paie sont générées au format **PDF sécurisé**.
- Le fichier PDF est **protégé par mot de passe** : le mot de passe est le **CIN de l’employé**.
- Le mot de passe est **envoyé automatiquement par SMS** via un modem connecté au serveur (Gammu).
- L’employé reçoit :
  - le **fichier PDF par email**,
  - le **mot de passe par SMS**.

---

## Structure des fichiers PDF

Les fiches de paie sont organisées dans un fichier ZIP selon cette structure :
```
paie.zip/
└── paie/
└── {nom_entreprise}/
└── {numero_employe}/
└── fiche_de_paie_modele.pdf
```

---

## Prérequis 

- **Back-end** : Symfony (PHP)
- **Base de données** : MySQL
- **Envoi SMS** : Gammu 1.33.0 + Modem 4G (port COM)

---

## Comment utiliser la plateforme ?

### 1️⃣ Cloner et préparer le projet

1. Télécharger ou cloner le projet Symfony.
2. Exécuter les commandes suivantes dans le terminal à la racine du projet :

```
symfony console doctrine:database:create
symfony console make:migration # Si cette commande échoue alors éxecuter: symfony console doctrine:schema:update --force
symfony console doctrine:migrations:migrate
```


### 2️⃣ Configurer le modem SMS avec Gammu
  1. Brancher votre modem 4G (clé USB) et identifier le port COM utilisé (ex: COM4) via le gestionnaire de périphériques.
  2. Créer un fichier gammurc (sans extension) dans le même dossier que gammu.exe, avec le contenu suivant :
```
[gammu]
port = COM4
connection = at19200
```

### 3️⃣ Automatiser la sauvegarde de la base de données
  1. Créer un fichier script.bat avec le contenu suivant :
```
  @echo off
  setlocal
  
  :: Lire le chemin depuis config.txt
  set /p BACKUP_PATH=<"C:\Users\Pc\Desktop\KPMG\Export\config.txt"
  
  :: Créer un horodatage
  set TIMESTAMP=%DATE:~-4%%DATE:~3,2%%DATE:~0,2%_%TIME:~0,2%%TIME:~3,2%
  set TIMESTAMP=%TIMESTAMP: =0%
  
  :: Exporter la base
  "C:\xampp\mysql\bin\mysqldump.exe" -u root KPMG > "%BACKUP_PATH%\backup_%TIMESTAMP%.sql"
  
  endlocal
```
  2. Dans le même dossier, créer un fichier config.txt avec le chemin de base où sauvgarder la base de donnée.

#### 4️⃣ Lancer le projet
  1. Executer la commande ci-dessous à la racine du projet:
```
-symfony server:start 
```
  2. Finalement acceder à la plateforme grace a l'url: http://127.0.0.1:8000/signin

## Auteur

Projet réalisé par **Sami Ben Abdelkader**.  
Contact : [smail.benabdelkader@gmail.com](mailto:smail.benabdelkader@gmail.com)

---

