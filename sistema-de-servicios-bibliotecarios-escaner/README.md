- Clean up commands
```
docker container stop <CONTAINER_ID>
docker container remove <CONTAINER_ID>
docker image rm uttn/biblioteca:latest
```

- Build Command
```
docker build https://<user>:<password>@gitlab.uttn.app/uttn/va/sistema-de-servicios-bibliotecarios.git#mejoras -t uttn/biblioteca:latest
```

- Run Command
```
docker run -d --restart unless-stopped -p 22512:80 uttn/biblioteca:latest
```