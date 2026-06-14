document.addEventListener("DOMContentLoaded", () => {
    // Back to Top Button
    const backToTop = document.createElement("button");
    backToTop.innerText = "⬆ Vrh";
    backToTop.id = "backToTop";
    document.body.appendChild(backToTop);

    backToTop.style.position = "fixed";
    backToTop.style.bottom = "20px";
    backToTop.style.right = "20px";
    backToTop.style.padding = "10px";
    backToTop.style.background = "#00bcd4";
    backToTop.style.color = "white";
    backToTop.style.border = "none";
    backToTop.style.borderRadius = "5px";
    backToTop.style.cursor = "pointer";
    backToTop.style.display = "none";

    window.addEventListener("scroll", () => {
        if (window.scrollY > 200) {
            backToTop.style.display = "block";
        } else {
            backToTop.style.display = "none";
        }
    });

    backToTop.addEventListener("click", () => {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });

    // Smooth Scrolling for Navigation
    const links = document.querySelectorAll("nav a");
    links.forEach(link => {
        link.addEventListener("click", (e) => {
            e.preventDefault();
            const targetId = link.getAttribute("href").substring(1);
            const targetSection = document.getElementById(targetId);

            if (targetSection) {
                window.scrollTo({
                    top: targetSection.offsetTop - 50,
                    behavior: "smooth"
                });
            }
        });
    });

    // Project Card Hover Effect and Click Redirection
    const projectCards = document.querySelectorAll(".project-card");
    projectCards.forEach(card => {
        card.addEventListener("mouseenter", () => {
            card.style.transform = "scale(1.1)";
            card.style.transition = "transform 0.3s ease-in-out";
        });
        card.addEventListener("mouseleave", () => {
            card.style.transform = "scale(1)";
        });

        card.addEventListener("click", () => {
            const projectPage = card.getAttribute("data-project-page");
            if (projectPage) {
                window.location.href = projectPage;
            }
        });
    });

    // Problems Card Hover Effect and Click Redirection
    const problemContainer = document.querySelectorAll(".problems");
    problemContainer.forEach(card => {
        card.addEventListener("mouseenter", () => {
            card.style.transform = "scale(1.1)";
            card.style.transition = "transform 0.3s ease-in-out";
        });
        card.addEventListener("mouseleave", () => {
            card.style.transform = "scale(1)";
        });

        card.addEventListener("click", () => {
            const projectPage = card.getAttribute("data-project-page");
            if (projectPage) {
                window.location.href = projectPage;
            }
        });
    });

    // Load C++ Code Snippets from External Files
    document.querySelectorAll(".cppCode").forEach((element) => {
        const filePath = element.getAttribute("data-src"); // Get file path

        fetch(filePath)
            .then(response => response.text())
            .then(data => {
                element.textContent = data; // Insert code
            })
            .catch(error => console.error("Error loading file:", filePath, error));
    });
    
});
