document.addEventListener("DOMContentLoaded", function() {
    let currentProblemNumber = null;

    // Dynamically create problem links
    const problemLinksContainer = document.getElementById("problem-links");
    for (let i = 1; i <= 5; i++) {
        const problemDiv = document.createElement("div");
        problemDiv.classList.add("problem");
        problemDiv.setAttribute("data-location", `/RPZ/CODES/REGIONALNO/2025/${i}.cpp`);
        problemDiv.textContent = `Problem ${i}`;
        problemLinksContainer.appendChild(problemDiv);
    }

    // Add event listeners for each problem link
    const problems = document.querySelectorAll(".problem");
    problems.forEach(problem => {
        problem.addEventListener("click", function() {
            const locationPath = problem.getAttribute("data-location");
            showSolution(locationPath);
        });
    });

    // Show solution function
    function showSolution(locationPath) {
        // Reset selected class for all problems
        document.querySelectorAll(".problem").forEach(problem => {
            problem.classList.remove("selected");
        });

        const selectedProblem = Array.from(document.querySelectorAll(".problem")).find(problem => problem.getAttribute("data-location") === locationPath);
        selectedProblem.classList.add("selected");

        const problemNumber = selectedProblem.textContent.trim().split(" ")[1]; // Extract number

        // Fetch and display solution
        fetch(locationPath)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.text();
            })
            .then(code => {
                const solutionElement = document.getElementById("solution");
                solutionElement.textContent = code;
                document.getElementById("solution-container").scrollTop = 0;
                document.getElementById("download-btn").style.display = "inline-block";
                document.getElementById("copy-btn").style.display = "inline-block";

                currentProblemNumber = problemNumber;
            })
            .catch(error => {
                console.error("Error loading solution:", error);
                const solutionElement = document.getElementById("solution");
                solutionElement.textContent = "❌ Greška pri učitavanju rešenja.";
                document.getElementById("download-btn").style.display = "none";
                document.getElementById("copy-btn").style.display = "none";
            });
    }

    // Download solution functionality
    function downloadSolution() {
        const solutionText = document.getElementById("solution").textContent;
        if (!solutionText) return;

        if (currentProblemNumber === null) {
            console.error("Problem number not found.");
            return;
        }

        const blob = new Blob([solutionText], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = url;
        link.download = `problem${currentProblemNumber}.cpp`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    // Expose download solution to global scope
    window.downloadSolution = downloadSolution;

    // Copy solution to clipboard functionality
    document.getElementById("copy-btn").addEventListener("click", function() {
        const solutionText = document.getElementById("solution").innerText;
        const tempInput = document.createElement("input");
        document.body.appendChild(tempInput);
        tempInput.value = solutionText;
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
    });

    // Toggle solution container size
    const toggleSizeButton = document.getElementById('toggle-size-btn');
    const solutionContainer = document.getElementById('solution-container');

    toggleSizeButton.addEventListener('click', function() {
        solutionContainer.classList.toggle('expanded');
        toggleSizeButton.textContent = solutionContainer.classList.contains('expanded') ? "Collapse Solution" : "Expand Solution";
    });
});
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});
