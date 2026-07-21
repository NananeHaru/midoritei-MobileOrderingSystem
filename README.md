# みどり亭 - 注文管理システム

Spring Bootで作成した居酒屋向けモバイルオーダー＆店舗管理システムです。  
客席でのQR注文、メニュー閲覧、コース選択、スタッフ用管理画面、レジシステム連携機能などを備えています。

---

## 1. 動作環境・必要ソフト（ダウンロードリンク付き）

動作に必要な各種ソフトウェアと公式ダウンロードリンクの一覧です。

| ソフトウェア | 推奨バージョン | ダウンロードリンク | 備考 |
|---|---|---|---|
| **Java (JDK)** | **Java 21** | [Adoptium (Temurin 21)](https://adoptium.net/temurin/releases/?version=21) / [Oracle JDK 21](https://www.oracle.com/java/technologies/downloads/#java21) | 必須 |
| **Build Tool** | **Maven 3.8+** | [Apache Maven 公式](https://maven.apache.org/download.cgi) | ※Windowsは **Binary zip archive (`-bin.zip`)** を選択 |
| **Database** | **MariaDB 10.4+** | [MariaDB 公式ダウンロード](https://mariadb.org/download/) | 本番・テスト用 (prod) |
| *(代替DB)* | **MySQL 8.0+** | [MySQL Community Installer](https://dev.mysql.com/downloads/installer/) | MariaDBの代わりに使用可 |
| *(環境構築ツール)* | **XAMPP** | [XAMPP 公式ダウンロード](https://www.apachefriends.org/jp/download.html) | MariaDB/MySQL環境を手軽に作る場合 |
| *(簡易DB)* | **H2 Database** | *(設定不要・内蔵)* | 開発・簡易デモ用 |

---

## 2. データベースの作成・初期化手順

### A. MariaDB / MySQL を使用する場合 (本番・環境構築)

1. **データベースの作成**  
   MariaDB（またはMySQL）にログインし、データベースを作成します。

   ```sql
   CREATE DATABASE IF NOT EXISTS midoritei DEFAULT CHARACTER SET utf8mb4;
   ```

2. **テーブルと初期データの投入**  
   プロジェクト直下の `src/main/resources/schema-mariadb.sql` を実行して、テーブル作成と初期データを投入します。

   ```bash
   mysql -u root -p midoritei < src/main/resources/schema-mariadb.sql
   ```
   *(※ DBeaver、HeidiSQL、phpMyAdmin などのGUIツールで `schema-mariadb.sql` の中身を開いて実行しても構いません)*

3. **接続設定の確認**  
   `src/main/resources/application-prod.properties` 内のDB接続情報（ユーザー名・パスワード）が自環境と一致しているか確認します。
   ```properties
   spring.datasource.url=jdbc:mariadb://localhost:3306/midoritei
   spring.datasource.username=root
   spring.datasource.password=your_password
   ```

---

### B. H2 インメモリDBを使用する場合 (簡易起動・デモ用)

設定不要です。アプリ起動時に `src/main/resources/schema.sql` が自動適用されます。

---

## 3. アプリケーションの起動手順

### 手順 1: プロジェクトフォルダに移動

```bash
cd workspace/----
```

### 手順 2: ビルド＆起動

#### 【パターン1】 MariaDB (prodプロファイル) で起動する場合
```bash
./mvnw spring-boot:run -Dspring-boot.run.profiles=prod
```
または Maven を直接使用:
```bash
mvn spring-boot:run -Dspring-boot.run.profiles=prod
```

#### 【パターン2】 H2（開発用メモリDB）で起動する場合
```bash
mvn spring-boot:run
```

---

## 4. 画面アクセス URL ＆ ログイン情報

アプリ起動後、ブラウザで以下のURLにアクセスしてください。

- **客側 画面 (トップ・席開設)**: [http://localhost:8080/seating/start](http://localhost:8080/seating/start)
- **客側 メニュー画面**: [http://localhost:8080/menu](http://localhost:8080/menu)
- **スタッフ管理画面**: [http://localhost:8080/staff/login](http://localhost:8080/staff/login)

### 🔑 スタッフ用ログイン情報
- **スタッフID**: `staff01`
- **パスワード**: `password`

---

## 5. 主な機能一覧

1. **客側機能**
   - QRコード/席選択による注文開始・人数・コース（2時間/3時間等）選択
   - カテゴリー別商品閲覧・オプション選択（ロック/水割り/タレ/塩など）
   - カート機能・注文送信
   - 注文履歴・会計金額の確認

2. **スタッフ管理機能**
   - リアルタイム座席状況一覧・提供ステータス管理
   - メニュー・商品マスタ編集（価格・カテゴリ・販売状況など）
   - 注文の提供状況更新（未提供/提供済/キャンセル）

3. **外部連携機能**
   - レジシステム連携 API (会計中ステータスの連携など)

---

## 6. ディレクトリ構成

```text
src/
├── main/
│   ├── java/com/example/midoritei/
│   │   ├── controller/   # Web画面・APIのコントローラー
│   │   ├── entity/       # 商品・カート等のエンティティ
│   │   ├── service/      # 注文・座席・商品のビジネスロジック
│   │   └── MidoriteiApplication.java
│   └── resources/
│       ├── schema-mariadb.sql  # MariaDB用テーブル定義・初期データ
│       ├── schema.sql          # H2用テーブル定義
│       ├── application.properties
│       └── templates/          # HTMLテンプレート (Thymeleaf)
```
