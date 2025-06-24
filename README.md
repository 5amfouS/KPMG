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


2️⃣ Configurer le modem SMS avec Gammu
Brancher votre modem 4G (clé USB) et identifier le port COM utilisé (ex: COM4) via le gestionnaire de périphériques.

Créer un fichier gammurc (sans extension) dans le même dossier que gammu.exe, avec le contenu suivant :
[gammu]
port = COM4
connection = at19200

3️⃣ Automatiser la sauvegarde de la base de données
Créer un fichier script.bat avec le contenu suivant :

bat
Copier
Modifier

## Comment utiliser la plateforme?

1-Télécharger l'intégralité du projet
2-Faire les commandes:
-symfony console doctrine:database:create
-symfony.exe console make:migration 
-symfony.exe console doctrine:schema:update --force ---> si la commande précédente ne passe pas.
-symfony console doctrine:migrations:migrate
2-Brancher et identifier le port associé au modem d'envoi d'sms 
3-Créer un fichier gammurc (sans extension) dans le même dossier que l'executable gammu.exe :
  [gammu]
  port = COM4 --> Selon le port identifié dans l'étape 2.
  connection = at19200
4-Creer une instance d'une fréquence d'un jour dans le planificateur de taches et pointer sur le fichier script.bat:
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
5-Creer un fichier config.txt dans le meme dossier que script.bat et contenant le lien de sauvegarde de la base de donnée.
6- Lancer le serveur symfony avec la commande suivante:
-symfony server:start et acceder à l'adresse suivante: http://127.0.0.1:8000/signin



## Auteur

Projet réalisé par **Sami Ben Abdelkader**.  
Contact : [smail.benabdelkader@gmail.com](mailto:smail.benabdelkader@gmail.com)

---

