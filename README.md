# みどり亭 - 注文管理システム

Spring Boot を使用した飲食店向けの注文管理システムです。QR コードスキャンによる席開設、メニュー管理、注文管理の機能を提供します。

## 概要

- **人数・コース選択**: QR コード読み込み後、人数とコースを選択
- **メニュー表示**: 商品マスタから取得したメニュー一覧を表示
- **注文管理**: 注文かごに商品を追加し、合計金額を計算・注文確定

## 必要な環境

- Java 17 以上
- Maven 3.6 以上
- MySQL 5.7 以上（本番環境）

## セットアップ方法

### 1. プロジェクトのクローン・配置

```bash
cd みどり亭
```

### 2. 依存関係のインストール

```bash
mvn clean install
```

### 3. アプリケーションの起動（開発環境）

```bash
mvn spring-boot:run
```

開発環境では H2 インメモリデータベースが使用されます。

### 4. ブラウザでアクセス

```
http://localhost:8080
```
http://localhost:8080/staff/

スタッフID staff01
パスワード password

## プロジェクト構成

```
src/main/java/com/example/midoritei/
├── MidoriteiApplication.java          # メインアプリケーションクラス
├── CustomerIdGenerator.java           # 顧客ID自動採番
├── entity/                            # ① エンティティクラス
│   ├── Product.java                   # 商品マスタ
│   ├── CartItem.java                  # 注文かご
│   └── CustomerSession.java           # 顧客セッション
├── service/                           # ② ビジネスロジック
│   ├── ProductService.java
│   ├── SeatingService.java
│   └── OrderService.java
└── controller/                        # ③ コントローラ
    ├── ProductController.java
    └── SeatingController.java

src/main/resources/
├── templates/                         # ④ HTML テンプレート
│   ├── index.html
│   ├── seating/
│   │   ├── members.html
│   │   └── course.html
│   └── menu.html
├── static/
│   └── images/                        # ⑤ 商品画像
├── application.properties             # 開発環境設定
└── application-prod.properties        # 本番環境設定
```

## 利用可能なプロファイル

### 開発環境（デフォルト）
```bash
mvn spring-boot:run
```
- H2 インメモリデータベース使用
- Thymeleaf キャッシュ無効
- デバッグログ有効
- H2 Console: http://localhost:8080/h2-console

### 本番環境
```bash
mvn spring-boot:run -Dspring-boot.run.arguments="--spring.profiles.active=prod"
```
- MySQL データベース使用
- Thymeleaf キャッシュ有効
- ログレベル: WARN

## ビルドと実行

### JAR ファイルとしてビルド

```bash
mvn clean package
```

### ビルドされた JAR を実行

```bash
java -jar target/midoritei-1.0.0.jar
```

## 主要な API エンドポイント

| エンドポイント | メソッド | 説明 |
|---|---|---|
| `/` | GET | 注文開始画面 |
| `/seating/members` | GET | 人数選択画面 |
| `/seating/course` | GET | コース選択画面 |
| `/seating/register` | POST | 席開設 |
| `/menu` | GET | メニュー一覧 |

## トラブルシューティング

### ポート 8080 が使用中の場合

```bash
mvn spring-boot:run -Dspring-boot.run.arguments="--server.port=8081"
```

### データベースエラーが発生した場合

開発環境では自動的に H2 データベースが初期化されます。
設定ファイルで `spring.jpa.hibernate.ddl-auto=create-drop` を使用しているため、アプリケーション起動時にテーブルが作成されます。

## ライセンス

未定

## 参考資料

- [Spring Boot Official Documentation](https://spring.io/projects/spring-boot)
- [Thymeleaf Documentation](https://www.thymeleaf.org/)
- [Spring Data JPA Documentation](https://spring.io/projects/spring-data-jpa)
