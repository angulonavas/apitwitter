<?php

namespace AppBundle\Controller;

//require "../vendor/autoload.php";

use Abraham\TwitterOAuth\TwitterOAuth;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller {

    // Definición de constantes:

    // clave de autenticación y token de acceso
    const CONSUMER_KEY = 'Rl759vUc61HuoCVzHdF3R9tWS';
    const CONSUMER_SECRET = 'TCmU55VRlXD4jZ5tRT4EyBrqqGPItypnnGWvhjuzRPzAw31ay8';
    const ACCESS_TOKEN = '1366752835-mjsStkwnkGwBoqAStjhQKBiK758aeJbonJyZ8Uz';
    const ACCESS_TOKEN_SECRET = 'G7W7YZ9vbruIQ2PL5nWkYWSJizOy9XnfRwoI06Qv93XgZ';

    const TWEET_LONG = 280;   // 280 caracteres como máximo por tweet
    const CACHE_EXPIRE = 300; // 5 minutos

    /*
     * método privado que crea un array con la información que se desea devolver:
     * NOTA: en teoŕía los retweets y los likes se deberían poder obtener directamente del primer nivel de datos, pero finalmente
     * se obtiene dicha información de un segundo nivel llamado 'retweeted_status' ya que desde user no se puede recoger 
     * la cantidad real.
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
     * Servicio que crea busca el tweet idenficado con el id "id" del usuario "usuario"
     * @Route("/", name="homeroot")
     */
    public function raizAction(Request $request) {
        return $this->render('base.html.twig');
    }

    /**
     * Servicio que crea busca el tweet idenficado con el id "id" del usuario "usuario"
     * @Route("/{usuario}/:{id}", name="tweet_buscar")
     */
    public function getTweet(Request $request, $usuario, $id) {

        try {
            // Conectamos al api de twitter
            $connection = new TwitterOAuth($this::CONSUMER_KEY, $this::CONSUMER_SECRET, $this::ACCESS_TOKEN, $this::ACCESS_TOKEN_SECRET);

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

            // configuramos las cabeceras de la respuesta
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD');
            $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
            $response->headers->addCacheControlDirective('must-revalidate', true);

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
     * Implementamos aquí dos servicios:
     *
     * Servicio que devuelve todos los tweets del usuario "usuario"
     * @Route("/{usuario}", name="tweets_buscar")
     *
     * Servicio que devuelve los "n" últimos tweets del usuario "usuario"
     * @Route("/{usuario}/{n}", name="tweets_buscar_n")
     */   
    public function getTweets(Request $request, $usuario, $n = 0) {

        try {
            // Conectamos al api de twitter
            $connection = new TwitterOAuth($this::CONSUMER_KEY, $this::CONSUMER_SECRET, $this::ACCESS_TOKEN, $this::ACCESS_TOKEN_SECRET);

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

            // configuramos las cabeceras de la respuesta
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD');
            $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
            $response->headers->addCacheControlDirective('must-revalidate', true);

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
     * Servicio que crea un tweet para el usuario "usuario" con el mensaje "texto" siempre que el token de seguridad "token" coincida
     * @Route("/{usuario}/{token}/{texto}", name="tweet_crear")
     */
    public function setTweet(Request $request, $usuario, $token, $texto) {

        try {

            // Comprobar la coincidencia de tokens de seguridad
            if ($token != $this::ACCESS_TOKEN_SECRET) throw new Exception ('ERROR: permiso denegado', 2);

            // Comprobando que la longitud del texto no supera los 280 caracteres
            if (mb_strlen($texto) > $this::TWEET_LONG) throw new Exception("ERROR: el texto ha superado los ".$this::TWEET_LONG.' caracteres', 3);           

            // Conectamos al api de twitter
            $connection = new TwitterOAuth($this::CONSUMER_KEY, $this::CONSUMER_SECRET, $this::ACCESS_TOKEN, $this::ACCESS_TOKEN_SECRET);

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

            // configuramos las cabeceras de la respuesta
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, PUT, HEAD');
            $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
            $response->headers->addCacheControlDirective('must-revalidate', true);

            // Devolvemos el response
            return $response;

        } catch (Exception $e) {
            $array = ['status' => 'ERROR','errors' => $e->getMessage(),];
            return new JsonResponse($array, 200);
        }           
    }    


    /**
     * Servicio que elimina un tweet del usuario "usuario" con el id "id" siempre que el token de seguridad "token" coincida
     * @Route("/{usuario}/{token}/eliminar/:{id}", name="tweet_eliminar")
     */
    public function delTweet(Request $request, $usuario, $token, $id) {

        try {

            // Comprobar la coincidencia de tokens de seguridad
            if ($token != $this::ACCESS_TOKEN_SECRET) throw new Exception ('ERROR: permiso denegado', 2);

            // Conectamos al api de twitter
            $connection = new TwitterOAuth($this::CONSUMER_KEY, $this::CONSUMER_SECRET, $this::ACCESS_TOKEN, $this::ACCESS_TOKEN_SECRET);

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

            // configuramos las cabeceras de la respuesta
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, DELETE, HEAD');
            $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
            $response->headers->addCacheControlDirective('must-revalidate', true);

            // Devolvemos el response
            return $response;

        } catch (Exception $e) {
            $array = ['status' => 'ERROR','errors' => $e->getMessage(),];
            return new JsonResponse($array, 200);
        }          
    }      

}
