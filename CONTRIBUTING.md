# Contribuer

1. Forkez le depot et creez une branche : `git checkout -b feat/ma-fonctionnalite`
2. Faites des commits clairs : `feat:`, `fix:`, `docs:`, `refactor:`
3. Verifiez que le site fonctionne en local (voir le README)
4. Ouvrez une Pull Request en decrivant votre changement

## Regles
- Ne jamais committer le fichier `.env`
- Requetes SQL preparees (PDO) uniquement
- Echapper toute sortie HTML avec `e()`