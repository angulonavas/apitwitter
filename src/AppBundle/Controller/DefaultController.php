<?php

namespace AppBundle\Controller;

use Abraham\TwitterOAuth\TwitterOAuth;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller {

    // Definición de constantes:
    const TWEET_LONG = 280;   // 280 caracteres como máximo por tweet
    const CACHE_EXPIRE = 300; // 5 minutos para que expire la caché

    /*
     * método privado que crea un array con la información que se desea devolver:
     * NOTA: en teoŕía los retweets y los likes se deberían poder obtener directamente del primer nivel de datos, pero 
     * finalmente se obtiene dicha información de un segundo nivel llamado 'retweeted_status' ya que desde user no se ha 
     * podido obtener la cantidad real
     */
    private function formatear_tweet($array_bruto) {
        return [
            'nombre' => $array_bruto['user']['name'],
            'alias' => $array_bruto['user']['screen_name'],
            'texto' => $array_bruto['text'],
            'fecha' => $array_bruto['created_at'],
            'likes' => (isset($array_bruto['retweeted_status']['favorite_count'])) ? $array_bruto['retweeted_status']['favorite_count'] : $array_bruto['favorite_count'],
            'retweets' => (isset($array_bruto['retweeted_status']['retweet_count'])) ? $array_bruto['retweeted_status']['retweet_count'] : $array_bruto['retweet_count'],
        ];
    }


    /**
     * Servicio de autenticación inicial del usuario. Crea y devuelve el token al usuario
     * @Route("/", name="homeroot")     
     * @Route("/api/login_check", name="login")
     */
    public function loginAction(Request $request) {

        try {                

            // obtenemos el usuario 
            $user = $this->get('security.token_storage')->getToken()->getUser();

            // creamos el token con el nombre de usuario y tiempo de expiración de 1 hora
            $token = $this->get('lexik_jwt_authentication.encoder')->encode([
                'username' => $user->getUsername(),
                'exp' => time() + 3600 // 1 hour expiration
            ]);

            // si no se ha podido crear el token es porque ha fallado la autenticación
            if (!$token) throw new Exception ('ERROR: No es posible autentificar', 1);            

            // se informará del estado exitoso
            $array = ['status' => 'ok'];

            // se crea la respuesta json
            $response = new JsonResponse($array, 200);        

            // se añade a la cabecera del response el token de autorización
            $response->headers->set('Authorization', 'Bearer '.$token);

            // devolvemos el response
            return $response;

        } catch (Exception $e) {
            $array = ['status' => 'ERROR','errors' => $e->getMessage(),];
            return new JsonResponse($array, 200);
        }               
    }


    /**
     * Servicio que busca el tweet idenficado con el id "id" del usuario "usuario"
     * @Route("api/buscar/{usuario}/{id}", name="tweet_buscar")
     */
    public function getTweetAction(Request $request, $usuario, $id) {

        try {

            // obtenemos el token de autorización
            $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));

            // comprobamos la autorización
            $data = $this->get('lexik_jwt_authentication.encoder')->decode($token);

            // Conectamos al api de twitter
            $connection = new TwitterOAuth(
                $this->getParameter('consumer_key'), $this->getParameter('consumer_key_secret'), 
                $this->getParameter('access_token'), $this->getParameter('access_token_secret')
            );

            // Solicitamos el tweet del usuario "usuario" cuyo id es "id"
            $tweet = $connection->get("statuses/show", ['id' => $id]);
    
            // si no se ha recibido nada, ha habido un error en la API de twitter
            if (!$tweet) throw new Exception ('ERROR: No se ha recibido información de twitter', 1);

            // formateamos el objeto devuelto por twitter en un array en bruto
            $array_bruto = json_decode(json_encode($tweet), true);

            // si twitter devuelve errores es que no se ha podido eliminar el tweet
            if (isset($array_bruto['errors'])) throw new Exception ('ERROR: No se ha podido encontrar el tweet', 2);            
            // la información recibida la estructuramos para devolver sólo lo deseado
            $array = $this->formatear_tweet($array_bruto);

            // la información estructurada la codificacmos en json para ser devuelta
            $response = new JsonResponse($array, 200);

            // configuramos las cabeceras de la respuesta para añadir el token
            $response->headers->set('Access-Control-Allow-Headers', 'Authorization');
            $response->headers->set('Authorization', 'Bearer '.$token);

            // Configuramos la respuesta (response) para almacenarla en cache
            $response->setPublic();
            $response->setMaxAge($this::CACHE_EXPIRE);
            $response->setSharedMaxAge($this::CACHE_EXPIRE);

            // Devolvemos el response
            return $response;

        } catch (Exception $e) {
            $array = ['status' => 'ERROR','errors' => $e->getMessage(),];
            return new JsonResponse($array, 200);
        }        
    }


   /**
     * Implementamos aquí dos servicios en uno:
     *
     * Servicio que devuelve todos los tweets del usuario "usuario"
     * @Route("api/buscar/{usuario}", name="tweets_buscar")
     *
     * Servicio que devuelve los "N" últimos tweets del usuario "usuario"
     * @Route("api/buscar/N/{usuario}/{n}", name="tweets_buscar_n")
     */   
    public function getTweetsAction(Request $request, $usuario, $n = 0) {

        try {
  
            // obtenemos el token de autorización
            $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));

            // comprobamos si el token es correcto
            $data = $this->get('lexik_jwt_authentication.encoder')->decode($token);

            // Conectamos al api de twitter
            $connection = new TwitterOAuth(
                $this->getParameter('consumer_key'), $this->getParameter('consumer_key_secret'), 
                $this->getParameter('access_token'), $this->getParameter('access_token_secret')
            );

            // Solicitamos todos los tweets del usuario "usuario"
            $tweets = $connection->get("statuses/user_timeline", ['screen_name' => $usuario, 'count' => $n]);
    
            // si no se ha recibido nada, ha habido un error en la API de twitter
            if (!$tweets) throw new Exception ('ERROR: No se ha recibido información de twitter', 1);

            // formateamos el objeto devuelto por twitter en un array en bruto
            $array_bruto = json_decode(json_encode($tweets), true);

            // la información recibida la estructuramos para devolver sólo lo deseado
            // en este caso será un vector de doce vectores. Cada uno de los cuales será un tweet
            $array = [];
            foreach ($array_bruto as $array_tweet) {
                $array[] = $this->formatear_tweet($array_tweet);
            }

            // la información estructurada la codificacmos en json para ser devuelta
            $response = new JsonResponse($array, 200);

            // configuramos las cabeceras de la respuesta para añadir el token
            $response->headers->set('Access-Control-Allow-Headers', 'Authorization');
            $response->headers->set('Authorization', 'Bearer '.$token);

            // Configuramos la respuesta (response) para almacenarla en cache
            $response->setPublic();
            $response->setMaxAge($this::CACHE_EXPIRE);
            $response->setSharedMaxAge($this::CACHE_EXPIRE);

            // Devolvemos el response
            return $response;

        } catch (Exception $e) {
            $array = ['status' => 'ERROR','errors' => $e->getMessage(),];
            return new JsonResponse($array, 404);
        }         

    }


   /**
     * Servicio que crea un tweet para el usuario "usuario" con el mensaje "texto". Sólo usuarios ROLE_ADMIN
     * @Route("api/enviar/{usuario}/{texto}", name="tweet_crear")
     */
    public function setTweetAction(Request $request, $usuario, $texto) {

        try {

            // obtenemos el token de autorización
            $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));

            // comprobamos si el token es correcto
            $data = $this->get('lexik_jwt_authentication.encoder')->decode($token);

            // Comprobando que la longitud del texto no supera los 280 caracteres
            if (mb_strlen($texto) > $this::TWEET_LONG) throw new Exception("ERROR: el texto ha superado los ".$this::TWEET_LONG.' caracteres', 3);           

            // Conectamos al api de twitter
            $connection = new TwitterOAuth(
                $this->getParameter('consumer_key'), $this->getParameter('consumer_key_secret'), 
                $this->getParameter('access_token'), $this->getParameter('access_token_secret')
            );

            // Solicitamos la creación de un nuevo tweet para el usuario
            $tweet = $connection->post("statuses/update", ['Name' => $usuario, 'status' => $texto]);
    
            // si no se ha recibido nada, ha habido un error en la API de twitter
            if (!$tweet) throw new Exception ('ERROR: No se ha podido crear el tweet', 1);

            // formateamos el objeto devuelto por twitter en un array en bruto
            $array_bruto = json_decode(json_encode($tweet), true);

            // la información recibida la estructuramos para devolver sólo lo deseado
            $array = $this->formatear_tweet($array_bruto);

            // la información estructurada la codificacmos en json para ser devuelta
            $response = new JsonResponse($array, 200);

            // configuramos las cabeceras de la respuesta para añadir el token
            $response->headers->set('Access-Control-Allow-Headers', 'Authorization');
            $response->headers->set('Authorization', 'Bearer '.$token);

            // Devolvemos el response
            return $response;

        } catch (Exception $e) {
            $array = ['status' => 'ERROR','errors' => $e->getMessage(),];
            return new JsonResponse($array, 200);
        }           
    }    


    /**
     * Servicio que elimina un tweet del usuario "usuario" con el id "id" solo usuario con el rol ROLE_ADMIN
     * @Route("api/eliminar/{usuario}/{id}", name="tweet_eliminar")
     */
    public function delTweetAction(Request $request, $usuario, $id) {

        try {

            // obtenemos el token de autorización
            $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));

            // comprobamos si el token es correcto
            $data = $this->get('lexik_jwt_authentication.encoder')->decode($token);

            // Conectamos al api de twitter
            $connection = new TwitterOAuth(
                $this->getParameter('consumer_key'), $this->getParameter('consumer_key_secret'), 
                $this->getParameter('access_token'), $this->getParameter('access_token_secret')
            );

            // Solicitamos la eliminación de un tweet del usuario
            $tweet = $connection->post("statuses/destroy", ['id' => $id]);
    
            // si no se ha recibido nada, ha habido un error en la API de twitter
            if (!$tweet) throw new Exception ('ERROR: No se ha podido eliminar el tweet', 1);

            // formateamos el objeto devuelto por twitter en un array en bruto
            $array_bruto = json_decode(json_encode($tweet), true);

            // si twitter devuelve errores es que no se ha podido eliminar el tweet
            if (isset($array_bruto['errors'])) throw new Exception ('ERROR: No se ha podido eliminar el tweet', 1);

            // la información recibida la estructuramos para devolver sólo lo deseado
            $array = $this->formatear_tweet($array_bruto);      

            // la información estructurada la codificacmos en json para ser devuelta
            $response = new JsonResponse($array, 200);

            // configuramos las cabeceras de la respuesta para añadir el token
            $response->headers->set('Access-Control-Allow-Headers', 'Authorization');
            $response->headers->set('Authorization', 'Bearer '.$token);

            // Devolvemos el response
            return $response;

        } catch (Exception $e) {
            $array = ['status' => 'ERROR','errors' => $e->getMessage(),];
            return new JsonResponse($array, 200);
        }          
    }      

}
