# line-chatbot-workshop

## setup

1. composer install

```
docker run -v ./src:/app --rm composer:2.7 composer install
```

2. copy .env.example

```
cp .env.example .env
```

3. compose up

```
docker compose up -d
```
