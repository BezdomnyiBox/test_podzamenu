<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ProductController extends AbstractController
{
    private function sortProducts(array $products, string $searchedArticle): array
    {
        $searchedArticleLower = mb_strtolower($searchedArticle);
    
        usort($products, function ($a, $b) use ($searchedArticleLower) {
            $aMatch = (mb_strtolower($a['article']) === $searchedArticleLower);
            $bMatch = (mb_strtolower($b['article']) === $searchedArticleLower);
    
            if ($aMatch === $bMatch) {
                return 0; // Если оба совпадают или оба не совпадают, сохраняем исходный порядок
            }
            return $aMatch ? -1 : 1; // Товары с совпадающим артикулом идут первыми
        });
    
        return $products;
    }
    
    /**
     * @Route("/product/search", name="product_search", methods={"GET","POST"})
     */
    public function searchByArticle(Request $request)
    {
        $error = null;
        $products = [];

        if ($request->isMethod('POST')) {
            // Получение данных из формы
            $articleNumber = $request->request->get('Article');
            $apiKey = $request->request->get('api_key'); // Получаем api_key от пользователя
            $searchedArticle = $articleNumber; // Сохраняем введенный артикул

            if (!$articleNumber || !$apiKey) {
                $error = 'Параметры "Article" и "api_key" обязательны.';
            } else {
                // Инициализация cURL
                $ch1 = curl_init();

                // 1. Получить все возможные бренды для заданного номера
                $fields = array("JSONparameter" => json_encode(['Article' => $articleNumber]));
                $url = "http://api.tmparts.ru/api/ArticleBrandList?" . http_build_query($fields);

                curl_setopt($ch1, CURLOPT_URL, $url);
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
                $headers = [
                    'Authorization: Bearer ' . $apiKey,
                ];
                curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);

                $response = curl_exec($ch1);
                $artList = json_decode($response, true);

                curl_close($ch1);

                if (isset($artList['Message']) && $artList['Message'] != "") {
                    $error = $artList['Message'];
                } elseif ($artList['BrandList'] == null) {
                    $error = 'Номер не найден.';
                } else {
                    // 2. По каждому найденному бренду выполнить проценку
                    $products = [];
                    foreach ($artList['BrandList'] as $brand) {
                        // Инициализация cURL для второго запроса
                        $ch = curl_init();
                
                        $fields = array("JSONparameter" => json_encode([
                            'Brand' => $brand['BrandName'],
                            'Article' => $artList['Article'],
                            'is_main_warehouse' => 0,
                        ]));
                        $url = "http://api.tmparts.ru/api/StockByArticle?" . http_build_query($fields);
                
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $headers = [
                            'Authorization: Bearer ' . $apiKey,
                        ];
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                        $response = curl_exec($ch);
                        
                        $artListWithPrices = json_decode($response, true);

                
                        curl_close($ch);
                
                        // Обработка и добавление результатов в $products
                        foreach ($artListWithPrices as $item) {
                            if (isset($item['warehouse_offers']) && is_array($item['warehouse_offers'])) {
                                foreach ($item['warehouse_offers'] as $offer) {
                                    $deliveryPeriodDays = $offer['delivery_period'] ?? 0;
                                    $deliveryPeriodSeconds = $deliveryPeriodDays * 86400;
                                    
                                    $price = $offer['price'] ?? 0;
                                    $priceOnCopeika = $price * 100;
                                    
                                    $products[] = [
                                        'brand' => $item['brand'] ?? '',
                                        'article' => $item['article'] ?? '',
                                        'name' => $item['article_name'] ?? '',
                                        'quantity' => $offer['quantity'] ?? 0,
                                        'price' => $priceOnCopeika,
                                        'delivery_duration' => $deliveryPeriodSeconds,
                                        'vendorId' => $offer['id'] ?? '',
                                        'warehouseAlias' => $offer['warehouse_code'] ?? '',
                                    ];
                                }
                            }
                        }

                        // После получения всех товаров, сортируем их
                        $products = $this->sortProducts($products, $articleNumber);

                        // Сохранение результатов в сессии
                        $session = $request->getSession();
                        $session->set('products', $products);
                        $session->set('Article', $searchedArticle);

                        return $this->redirectToRoute('product_search_results');
                    }
                }
            }  
        }

        
           
        // Отображение формы для GET-запросов
        return $this->render('product/search.html.twig', [
            'error' => $error,
            'last_article' => $request->request->get('Article', ''),
            'last_api_key' => $request->request->get('api_key', ''),
        ]);
    }

    public function showSearchResults(Request $request)
    {
        $session = $request->getSession();
        $products = $session->get('products', []);
        $searchedArticle = $session->get('Article');

        return $this->render('product/results.html.twig', [
            'products' => $products,
            'searchedArticle' => $searchedArticle,
        ]);
    }
}
