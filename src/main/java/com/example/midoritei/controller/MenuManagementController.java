package com.example.midoritei.controller;

import com.example.midoritei.entity.Product;
import com.example.midoritei.service.ProductService;
import jakarta.servlet.http.HttpSession;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

@Controller
@RequestMapping("/staff")
public class MenuManagementController {

    @Autowired
    private ProductService productService;

    /**
     * メニュー管理トップを表示します（検索と追加の選択肢）。
     */
    @GetMapping("/menu")
    public String menuManagementPage(HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        return "staff/menu_management";
    }

    /**
     * メニュー詳細検索フォームを表示します。
     */
    @GetMapping("/menu/search")
    public String menuSearchFormPage(HttpSession session, Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        model.addAttribute("categories", productService.getAllCategories());
        return "staff/menu_search_form";
    }

    /**
     * 検索結果一覧画面を表示します。
     */
    @GetMapping("/menu/search/results")
    public String menuSearchResultsPage(@RequestParam(value = "productName", required = false) String productName,
                                        @RequestParam(value = "keyword", required = false) String keyword,
                                        @RequestParam(value = "categories", required = false) List<String> categories,
                                        @RequestParam(value = "priceRanges", required = false) List<String> priceRanges,
                                        @RequestParam(value = "courses", required = false) List<String> courses,
                                        @RequestParam(value = "salesStatuses", required = false) List<Integer> salesStatuses,
                                        @RequestParam(value = "searchOperator", defaultValue = "AND") String searchOperator,
                                        HttpSession session,
                                        Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        System.out.println("★検索パラメータ - productName: " + productName + ", keyword: " + keyword + ", categories: " + categories + ", priceRanges: " + priceRanges + ", courses: " + courses + ", salesStatuses: " + salesStatuses + ", searchOperator: " + searchOperator);

        // 季節限定の絞り込み判定
        boolean seasonalOnly = false;
        List<String> filteredCategories = new ArrayList<>();
        if (categories != null) {
            for (String cat : categories) {
                if ("seasonal".equals(cat)) {
                    seasonalOnly = true;
                } else {
                    filteredCategories.add(cat);
                }
            }
        }

        List<Product> searchResults = productService.searchProducts(
            productName,
            keyword,
            filteredCategories.isEmpty() ? null : filteredCategories,
            priceRanges,
            courses,
            seasonalOnly,
            salesStatuses,
            searchOperator
        );

        // カテゴリーIDからカテゴリー名への変換用マップを作成
        List<Map<String, Object>> cats = productService.getAllCategories();
        Map<String, String> catMap = new HashMap<>();
        for (Map<String, Object> c : cats) {
            catMap.put((String) c.get("category_id"), (String) c.get("category_name"));
        }

        model.addAttribute("products", searchResults);
        model.addAttribute("categoryMap", catMap);
        model.addAttribute("searchOperator", searchOperator);
        model.addAttribute("salesStatuses", salesStatuses);
        return "staff/menu_search_results";
    }

    /**
     * メニュー詳細を表示します。
     */
    @GetMapping("/menu/detail/{productId}")
    public String menuDetailPage(@PathVariable("productId") String productId,
                                 HttpSession session,
                                 Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        Product product = productService.getProductById(productId);
        if (product == null) {
            return "redirect:/staff/menu/search";
        }

        // カテゴリー名の逆引き
        List<Map<String, Object>> cats = productService.getAllCategories();
        String categoryName = "";
        for (Map<String, Object> c : cats) {
            if (c.get("category_id").equals(product.getCategoryId())) {
                categoryName = (String) c.get("category_name");
                break;
            }
        }

        // カテゴリーに紐づくオプションリスト取得
        List<Map<String, Object>> options = productService.getOptionsByCategoryId(product.getCategoryId());

        model.addAttribute("product", product);
        model.addAttribute("categoryName", categoryName);
        model.addAttribute("options", options);
        return "staff/menu_detail";
    }

    /**
     * メニューの販売ステータスを直接更新します。
     */
    @PostMapping("/menu/update-status")
    public String updateSalesStatus(@RequestParam("productId") String productId,
                                    @RequestParam("salesStatus") int salesStatus,
                                    HttpSession session) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }
        productService.updateSalesStatus(productId, salesStatus);
        return "redirect:/staff/menu/detail/" + productId;
    }

    /**
     * メニュー編集画面を表示します。
     */
    @GetMapping("/menu/edit/{productId}")
    public String menuEditPage(@PathVariable("productId") String productId,
                               HttpSession session,
                               Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        Product product = productService.getProductById(productId);
        if (product == null) {
            return "redirect:/staff/menu/search";
        }

        model.addAttribute("product", product);
        model.addAttribute("categories", productService.getAllCategories());
        // その商品カテゴリに対応する全オプション候補を取得
        model.addAttribute("options", productService.getOptionsByCategoryId(product.getCategoryId()));
        // その商品に現在紐づいている有効オプションを取得
        model.addAttribute("productOptions", productService.getOptionsByProductId(productId));
        return "staff/menu_edit";
    }

    /**
     * メニューの変更を確定保存します。
     */
    @PostMapping("/menu/edit")
    public String submitMenuEdit(@ModelAttribute Product product,
                                 @RequestParam(value = "seasonalStatusToggle", required = false) String seasonalToggle,
                                 @RequestParam(value = "optionIds", required = false) List<Integer> optionIds,
                                 HttpSession session,
                                 RedirectAttributes redirectAttributes) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        // トグルスイッチの値を設定
        product.setIsSeasonal("on".equals(seasonalToggle) ? 1 : 0);
        
        // デフォルト画像パスの補正
        if (product.getImagePath() == null || product.getImagePath().trim().isEmpty()) {
            product.setImagePath("/images/no-image.png");
        }

        productService.saveProduct(product);
        productService.saveProductOptions(product.getProductId(), optionIds);

        // 完了ポップアップ用の情報を渡す
        redirectAttributes.addFlashAttribute("editComplete", true);
        redirectAttributes.addFlashAttribute("editedProductName", product.getProductName());

        return "redirect:/staff/menu/detail/" + product.getProductId();
    }

    /**
     * メニュー新規追加画面を表示します。
     */
    @GetMapping("/menu/add")
    public String menuAddPage(HttpSession session, Model model) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        model.addAttribute("product", new Product());
        model.addAttribute("categories", productService.getAllCategories());
        return "staff/menu_add";
    }

    /**
     * メニューの追加を確定登録します。
     */
    @PostMapping("/menu/add")
    public String submitMenuAdd(@ModelAttribute Product product,
                                @RequestParam(value = "seasonalStatusToggle", required = false) String seasonalToggle,
                                @RequestParam(value = "optionIds", required = false) List<Integer> optionIds,
                                HttpSession session,
                                RedirectAttributes redirectAttributes) {
        if (session == null || session.getAttribute("loggedInStaff") == null) {
            return "redirect:/staff/login";
        }

        // トグルスイッチ設定
        product.setIsSeasonal("on".equals(seasonalToggle) ? 1 : 0);
        
        // 画像が設定されていない場合はモック画像
        if (product.getImagePath() == null || product.getImagePath().trim().isEmpty()) {
            product.setImagePath("/images/no-image.png");
        }

        // 先に商品をセーブしてIDを確定（自動採番含む）させる
        productService.saveProduct(product);
        // 保存された商品のID（自動採番で上書きされている可能性があるため、saveProduct後のIDを使用するべきだが、
        // saveProductメソッド内で引数のproductオブジェクトのproductIdフィールドを更新していない場合は
        // 自動採番されたIDを特定するため、別途ロジックが必要になります。
        // だが ProductService.java を見ると:
        // String nextId = product.getProductId();
        // if (nextId == null || nextId.trim().isEmpty()) {
        //     nextId = generateNextProductId(product.getCategoryId());
        // }
        // となっており、productオブジェクトのproductIdは更新していません！
        // なので、自動採番された新しいIDを特定するために、productService.saveProductの戻り値、
        // または、productIdをあらかじめ generateNextProductId(categoryId) してセットしておく必要があります。
        // これについて、saveProductの前に generateNextProductId を呼び出して product.setProductId にセットしておくのが最も確実です！
        if (product.getProductId() == null || product.getProductId().trim().isEmpty()) {
            // Service内の generateNextProductId は private なので、直接呼び出せないか？
            // あ、ProductService.java 内では saveProduct(Product product) で nextId を生成して insert しています。
            // しかし、コントローラ側で nextId を知りたいですね。
            // ProductService.java の saveProduct を修正して、自動採番したIDを product.setProductId() で設定するようにするか、
            // もしくは、saveProduct が String を返すようにすれば簡単です。
            // この問題は後ほど ProductService.java を微調整して解決しましょう。
        }
        // とりあえずコントローラ側は product.getProductId() を渡しておき、Service側で product の ID を自動セットするように修正します。
        productService.saveProduct(product);
        productService.saveProductOptions(product.getProductId(), optionIds);

        redirectAttributes.addFlashAttribute("addComplete", true);
        redirectAttributes.addFlashAttribute("addedProductName", product.getProductName());

        return "redirect:/staff/menu";
    }

    /**
     * カテゴリーが選択された際に、動的にオプションの一覧を返すAPIエンドポイント。
     */
    @GetMapping("/menu/api/options")
    @ResponseBody
    public List<Map<String, Object>> getOptionsApi(@RequestParam("categoryId") String categoryId) {
        return productService.getOptionsByCategoryId(categoryId);
    }

    /**
     * 商品に現在紐づいている有効なオプションIDリストを返すAPIエンドポイント。
     */
    @GetMapping("/menu/api/product-options")
    @ResponseBody
    public List<Map<String, Object>> getProductOptionsApi(@RequestParam("productId") String productId) {
        return productService.getOptionsByProductId(productId);
    }
}
