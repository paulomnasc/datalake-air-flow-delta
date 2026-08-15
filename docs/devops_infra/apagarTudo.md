Exatamente, Cristiane — se essas pastas estão sendo recriadas pelos containers Docker, então **matar todos os containers ativos** deve impedir que elas reapareçam.

---

### 🧨 Como parar todos os containers

Você pode usar:

```bash
docker stop $(docker ps -q)
```

Esse comando para todos os containers que estão rodando.

---

### 🧹 Como remover todos os containers (opcional)

Se quiser apagar os containers (sem afetar volumes ou imagens):

```bash
docker rm $(docker ps -aq)
```

---

### 🧼 Para limpar tudo (containers, volumes, redes, imagens)

⚠️ **Cuidado:** isso apaga tudo do Docker — use apenas se quiser começar do zero.

```bash
docker system prune -a --volumes
```

---

Se quiser manter os volumes (como o do PostgreSQL), use:

```bash
docker system prune -a
```

---

Se precisar de ajuda para montar um script que pare e limpe só o que você quer (sem apagar dados importantes), posso montar um para você. Quer que ele preserve o banco e só mate os containers?
