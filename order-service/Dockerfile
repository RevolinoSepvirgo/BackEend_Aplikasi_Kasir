# Tahap 1: Build (Gunakan eclipse-temurin agar lebih stabil)
FROM maven:3.8.5-openjdk-17 AS build
WORKDIR /app
COPY . .
RUN mvn clean package -DskipTests

# Tahap 2: Run (Gunakan eclipse-temurin versi JRE yang ringan)
FROM eclipse-temurin:17-jre-jammy
WORKDIR /app

# Ambil file .jar hasil build dari tahap 1
COPY --from=build /app/target/*.jar app.jar

EXPOSE 8080
ENTRYPOINT ["java", "-jar", "app.jar"]