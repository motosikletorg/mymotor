<?php

namespace Modules\Bundle\CrawlerBundle\Controller;


use Dizt\Bundle\ToolkitBundle\Library\Utils;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{

    /**
     * @Route("/list")
     */
    public function listAction()
    {

        $data=$this->container->get('doctrine.dbal.default_connection')->fetchAll('select
                                                                        b.id as brandId,
                                                                        b.name as brandName,
                                                                        m.id as modelId,
                                                                        m.name as modelName,
                                                                        a.k as attrName,
                                                                        a.v as attrValue,
                                                                        a.model_year as modelYear

                                                                        from brand b
                                                                        inner join model m on b.id=m.brand_id
                                                                        inner join attribute a on a.brand_id=b.id and a.model_id=m.id
                                                                        order by brandId,modelId,attrName
                                                                        ');
        return $this->render('@Crawler/Crawler/list.html.twig',['rows'=>$data]);
    }

    /**
     * @Route("/hello/{name}")
     * @Template()
     */
    public function indexAction($name)
    {
        return array('name' => $name);

    }

    /**
     * @Route("/crawl-motorcular/")
     */
    public function crawlAction()
    {
        header('Access-Control-Allow-Origin: *');
        return $this->render('@Crawler/Crawler/motorcular.html.twig');
    }

    /**
     * @Route("/proxy")
     */
    public function proxyAction(Request $request)
    {
        $url = $request->get('url');
        $key = md5("crawl_url" . $request->getQueryString()) . time();
        $debug = $request->get('debug', false);
        $content = $this->get('memcached_cache')->fetch($key);
        if ($content === false) {
            $method = $request->get('method', 'get');
            $data = $request->get('data');
            $config = [
                // Base URI is used with relative requests
                'base_uri' => 'http://www.motorcular.com/',
                // You can set any number of default request options.
                'timeout' => 3000,
                'cookies' => true,
                'debug' => $debug
            ];
            $client = new Client($config);
            if ($method == "get") {
                $content = $client->get($url)->getBody()->__toString();
            } else {

                $data = json_decode($data, true);


                $content = $client->post($url, ['form_params' => $data, 'headers' => [
                    'Referer' => 'http://www.motorcular.com/tr/motosiklet-karsilastirma',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Host' => 'www.motorcular.com',
                    'Origin' => 'http://www.motorcular.com',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
                    'Cookie' => 'motorcular=a%3A5%3A%7Bs%3A10%3A%22session_id%22%3Bs%3A32%3A%22b8825e055ecf64c3e70d95577bab6a79%22%3Bs%3A10%3A%22ip_address%22%3Bs%3A14%3A%2246.196.107.220%22%3Bs%3A10%3A%22user_agent%22%3Bs%3A120%3A%22Mozilla%2F5.0+%28Macintosh%3B+Intel+Mac+OS+X+10_10_5%29+AppleWebKit%2F537.36+%28KHTML%2C+like+Gecko%29+Chrome%2F44.0.2403.157+Safari%2F537.3%22%3Bs%3A13%3A%22last_activity%22%3Bi%3A1440704775%3Bs%3A9%3A%22user_data%22%3Bs%3A0%3A%22%22%3B%7D0ce36a2e1df8dec1469204c38b15c117aa53fd47; __gads=ID=0e25d4e783544170:T=1440849734:S=ALNI_MY66mrhZdH0o4mrMpH1EHlXOx323g; __sonar=11286426173436072445; _gat=1; _ga=GA1.2.1347256828.1440087877']
                ])->getBody()->__toString();


            }

            $this->get('memcached_cache')->save($key, $content);

        }

        if (Utils::isJsonString($content)) {
            return new JsonResponse($content);

        }
        $response = new Response();
        $response->setContent($content);
        return $response;

    }

    /**
     * @Route("/finalize")
     */
    public function finalizeAction(Request $request)
    {
        $content = $request->request->all();
        $fp = fopen($this->get('kernel')->getRootDir() . "/../web.xtx", 'a+');
        ftruncate($fp, 0);
        fwrite($fp, ($content['content']));
        fclose($fp);
        var_dump($this->get('kernel')->getRootDir() . "/../web.xtx");
        exit;
    }

    /**
     * @Route("/import")
     */
    public function setContenAction()
    {


        $content = file_get_contents($this->get('kernel')->getRootDir() . "/../web.xtx");
        $db = $this->container->get('doctrine.dbal.default_connection');
        $brands = json_decode($content, true);
        $inserts = ["brand" => [], "model" => [], "attributes" => []];
        foreach ($brands as $brandId => $brandInfo) {


            $inserts["brand"][] = '(' . $brandInfo['brandId'] . ',"' . $brandInfo['brandName'] . '")';
            foreach ($brandInfo['models'] as $modelId => $modelInfo) {

                $inserts["model"][] = '(' . $modelInfo['modelId'] . ',' . $modelInfo['model2Id'] . ',"' . $modelInfo['modelName'] . '",' . $brandId . ')';
                $content = json_decode($modelInfo['years'], true);
                $properties = json_decode($modelInfo['years'], true)['data'];
                foreach ($properties as $attributes) {
                    $year = $attributes['yil'];
                    foreach ($attributes as $key => $value) {
                        $value = addslashes($value);
                        $inserts['attributes'][] = "('$key', '$value', $brandId, $modelId,$year)";
                    }
                }
            }
        }
        $db->exec('truncate table brand');
        $db->exec('truncate table model');
        $db->exec('truncate table attribute');

        $db->exec('
        INSERT IGNORE INTO brand(id,name)
        VALUES
        ' . implode(',', $inserts["brand"]) . "
        ON DUPLICATE KEY
        update name='" . $brandInfo['brandName'] . "'
        ");

        $db->exec('
        INSERT IGNORE INTO model(id,id2,name,brand_id)
        VALUES
        ' . implode(',', $inserts["model"]) . "
        ");
        $db->exec('
        INSERT IGNORE INTO attribute(k,v,brand_id,model_id,model_year)
        VALUES
        ' . implode(',', $inserts["attributes"]) . "
        ");
        echo "done";
        exit;
    }


}
