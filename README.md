


Repositórios no gitlab:

    API:

        git clone http://dti-gitlab.unilab.edu.br/dti/uniselecapi.git

    Página do Candidato:

        git clone http://dti-gitlab.unilab.edu.br/dti/uniselecwebsite.git

    Página do administrador:

        http://dti-gitlab.unilab.edu.br/dti/uniselecadminpage.git



Links:

    Ideal:

        Página do candidato:

            Produção:

                https://uniselec.unilab.edu.br

            Homologação:

                https://uniselec-staging.unilab.edu.br

        Página do Administrador:

            Produção:

                https://uniselec-bo.unilab.edu.br

            Homologação:

                https://uniselec-bo-staging.unilab.edu.br

        API:

            Produção:

                https://uniselec-api.unilab.edu.br

            Homologação:

                https://uniselec-api-staging.unilab.edu.br



O que eu consigo fazer em pouco tempo:

 php artisan l5-swagger:generate


    Página do Candidato:
        Produção:

            https://uniselec.jefponte.com

        Homologação:

            https://uniselec-staging.jefponte.com

        Produção (Link alternativo, serviço gratuito do Firebase):

            https://uniselec.web.app


    Página do administrador:

        Produção:
            https://uniselec-bo.jefponte.com

        Homologação:

            https://uniselec-bo-staging.jefponte.com

        Produção (Link alternativo, serviço gratuito do Firebase):


            https://uniselec-unilab-bo.web.app

    API:

            https://uniselec-api.jefponte.com

            https://uniselec-api-staging.jefponte.com



Iniciar Ambiente de desenvolvimento:

        composer install

        docker compose up -d

        sudo chmod -R 777 storage

        sudo chmod -R 777 bootstrap/cache


    Docker compose:

        docker compose up -d

    Executar migrations e seeds:

        docker exec -it uniselec-api bash -c "php artisan migrate"
        docker exec -it uniselec-api bash -c "php artisan db:seed"

        docker exec -it uniselec-api bash -c "php artisan db:seed --class=UserSeeder"


        docker exec -it uniselec-api bash -c "php artisan route:cache"

        docker exec -it uniselec-api bash -c "php artisan route:clear"


        docker exec -it uniselec-api bash -c "php artisan storage:link"




