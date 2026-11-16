-- 学習用の learning は POSTGRES_DB で作られる。開発用とテスト用をここで作る（docs/02_architecture.md 4章）
CREATE DATABASE learning_dev OWNER app;
CREATE DATABASE testing OWNER app;
