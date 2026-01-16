package com.example.order;

import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;
import java.util.Map;

@RestController
public class RootController {
    @GetMapping("/")
    public Map<String, String> healthCheck() {
        return Map.of(
            "status", "API Ready",
            "service", "Order Service (Java Spring Boot)",
            "database", "MongoDB Connected"
        );
    }
}